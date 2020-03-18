<?php

namespace App\Services;

use App\Models\Basket\BasketItem;
use App\Models\Delivery\Cargo;
use App\Models\Delivery\CargoStatus;
use App\Models\Delivery\Delivery;
use App\Models\Delivery\Shipment;
use App\Models\Delivery\ShipmentPackage;
use App\Models\Delivery\ShipmentPackageItem;
use App\Models\Delivery\ShipmentStatus;
use Exception;
use Greensight\Logistics\Dto\CourierCall\CourierCallInput\CourierCallInputDto;
use Greensight\Logistics\Dto\CourierCall\CourierCallInput\DeliveryCargoDto;
use Greensight\Logistics\Dto\CourierCall\CourierCallInput\SenderDto;
use Greensight\Logistics\Services\CourierCallService\CourierCallService;
use Greensight\Logistics\Services\DeliveryOrderService\DeliveryOrderService;
use Greensight\Store\Dto\StorePickupTimeDto;
use Greensight\Store\Services\PackageService\PackageService;
use Greensight\Store\Services\StoreService\StoreService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use MerchantManagement\Services\MerchantService\MerchantService;

/**
 * Класс-бизнес логики по работе с сущностями доставки:
 * - доставками
 * - отправлениями
 * - товарами отправлений
 * - коробками отправлений
 * - товарами коробок отправлений
 * - грузами
 * Class DeliveryService
 * @package App\Services
 */
class DeliveryService
{
    /**
     * Получить объект отправления по его id
     * @param  int  $deliveryId
     * @return Delivery|null
     */
    public function getDelivery(int $deliveryId): ?Delivery
    {
        return Delivery::find($deliveryId);
    }

    /**
     * Получить объект отправления по его id
     * @param  int  $shipmentId
     * @return Shipment|null
     */
    public function getShipment(int $shipmentId): ?Shipment
    {
        return Shipment::find($shipmentId);
    }

    /**
     * Получить объект груза по его id
     * @param  int  $cargoId
     * @return Cargo|null
     */
    public function getCargo(int $cargoId): ?Cargo
    {
        return Cargo::find($cargoId);
    }

    /**
     * Создать коробку отправления
     * @param int $shipmentId
     * @param  int  $packageId
     * @return ShipmentPackage|null
     */
    public function createShipmentPackage(int $shipmentId, int $packageId): ?ShipmentPackage
    {
        $shipment = $this->getShipment($shipmentId);
        if (is_null($shipment)) {
            return null;
        }

        /** @var PackageService $packageService */
        $packageService = resolve(PackageService::class);
        $package = $packageService->package($packageId);

        $shipmentPackage = new ShipmentPackage();
        $shipmentPackage->shipment_id = $shipment->id;
        $shipmentPackage->package_id = $packageId;
        $shipmentPackage->wrapper_weight = $package->weight;
        $shipmentPackage->width = $package->width;
        $shipmentPackage->height = $package->height;
        $shipmentPackage->length = $package->length;

        return $shipmentPackage->save() ? $shipmentPackage : null;
    }

    /**
     * Удалить коробку отправления со всем её содержимым
     * @param  int  $shipmentPackageId
     * @return bool
     */
    public function deleteShipmentPackage(int $shipmentPackageId): bool
    {
        /** @var ShipmentPackage $shipmentPackage */
        $shipmentPackage = ShipmentPackage::query()->where('id', $shipmentPackageId)->with('items')->first();

        return DB::transaction(function () use ($shipmentPackage) {
            foreach ($shipmentPackage->items as $item) {
                $item->delete();
            }

            return $shipmentPackage->delete();
        });
    }

    /**
     * Добавить/обновить/удалить элемент (собранный товар с одного склада одного мерчанта) коробки отправления
     * @param  int  $shipmentPackageId
     * @param  int  $basketItemId
     * @param  float  $qty
     * @param  int  $setBy
     * @return bool
     * @throws Exception
     */
    public function setShipmentPackageItem(int $shipmentPackageId, int $basketItemId, float $qty, int $setBy): bool
    {
        $ok = true;
        $shipmentPackageItem = ShipmentPackageItem::query()
            ->where('shipment_package_id', $shipmentPackageId)
            ->where('basket_item_id', $basketItemId)
            ->first();
        if (!$qty && !is_null($shipmentPackageItem)) {
            //Удаляем элемент из коробки отправления
            $ok = $shipmentPackageItem->delete();
        } else {
            /** @var BasketItem $basketItem */
            $basketItem = BasketItem::find($basketItemId);

            if ($basketItem->qty < $qty) {
                throw new Exception('Shipment package qty can\'t be more than basket item qty');
            } else {
                if (is_null($shipmentPackageItem)) {
                    $shipmentPackageItem = new ShipmentPackageItem;
                }
                $shipmentPackageItem->updateOrCreate([
                    'shipment_package_id' => $shipmentPackageId,
                    'basket_item_id' => $basketItemId,
                ], ['qty' => $qty, 'set_by' => $setBy]);
            }
        }

        return $ok;
    }

    /**
     * Проверить, что все товары отправления упакованы по коробкам, если статус меняется на "Собрано"
     * @param Shipment $shipment
     * @return bool
     */
    public function checkAllShipmentProductsPacked(int $shipmentId): bool
    {
        $shipment = $this->getShipment($shipmentId);
        $shipment->load('items.basketItem', 'packages.items');

        $shipmentItems = [];
        foreach ($shipment->items as $shipmentItem) {
            $shipmentItems[$shipmentItem->basket_item_id] = $shipmentItem->basketItem->qty;
        }

        foreach ($shipment->packages as $package) {
            foreach ($package->items as $packageItem) {
                $shipmentItems[$packageItem->basket_item_id] -= $packageItem->qty;
            }
        }

        return empty(array_filter($shipmentItems));
    }

    /**
     * Добавить отправление в груз
     * @param  int  $shipmentId
     * @throws Exception
     */
    public function addShipment2Cargo(int $shipmentId): void
    {
        $shipment = $this->getShipment($shipmentId);
        if (is_null($shipment)) {
            throw new Exception('Отправление не найдено');
        }
        if ($shipment->status != ShipmentStatus::STATUS_ASSEMBLED) {
            throw new Exception('Отправление не собрано');
        }
        if ($shipment->cargo_id) {
            throw new Exception('Отправление уже добавлено в груз');
        }

        /**
         * Если у отправления указана служба доставки на нулевой миле, то используем её для груза.
         * Иначе для груза используем службы доставки для доставки, к которой принадлежит отправление
         */
        $deliveryService = $shipment->delivery_service_zero_mile;
        if (!$deliveryService) {
            $shipment->load('delivery');
            $deliveryService = $shipment->delivery->delivery_service;
        }

        $cargoQuery = Cargo::query()
            ->select('id')
            ->where('merchant_id', $shipment->merchant_id)
            ->where('store_id', $shipment->store_id)
            ->where('delivery_service', $deliveryService)
            ->where('status', CargoStatus::STATUS_CREATED)
            ->orderBy('created_at', 'desc');
        if ($shipment->getOriginal('cargo_id')) {
            $cargoQuery->where('id', '!=', $shipment->getOriginal('cargo_id'));
        }
        $cargo = $cargoQuery->first();
        if (is_null($cargo)) {
            //todo Создание груза выделить в отдельный метод
            $cargo = new Cargo();
            $cargo->merchant_id = $shipment->merchant_id;
            $cargo->store_id = $shipment->store_id;
            $cargo->delivery_service = $deliveryService;
            $cargo->status = CargoStatus::STATUS_CREATED;
            $cargo->width = 0;
            $cargo->height = 0;
            $cargo->length = 0;
            $cargo->weight = 0;
            $cargo->save();
        }

        $shipment->cargo_id = $cargo->id;
        $shipment->save();
    }

    /**
     * Создать заявку на вызов курьера для забора груза
     * @param  Cargo  $cargo
     * @throws Exception
     */
    public function createCourierCall(Cargo $cargo): void
    {
        if ($cargo->status != CargoStatus::STATUS_CREATED) {
            throw new Exception('Груз не в статусе "Создан"');
        }
        if ($cargo->xml_id) {
            throw new Exception('Для груза уже создана заявка на вызов курьера с номером "' . $cargo->xml_id . '"');
        }
        if ($cargo->shipments->isEmpty()) {
            throw new Exception('Груз не содержит отправлений');
        }

        /** @var StoreService $storeService */
        $storeService = resolve(StoreService::class);
        $storeQuery = $storeService->newQuery()
            ->include('storeContact', 'storePickupTime');
        $store = $storeService->store($cargo->store_id, $storeQuery);
        if (is_null($store)) {
            //todo Добавить оповещение о невыгруженном грузе
            return;
        }

        /** @var MerchantService $merchantService */
        $merchantService = resolve(MerchantService::class);
        $merchant = $merchantService->merchant($store->merchant_id);

        $courierCallInputDto = new CourierCallInputDto();

        $senderDto = new SenderDto();
        $courierCallInputDto->sender = $senderDto;
        $senderDto->address_string = isset($store->address['address_string']) ? $store->address['address_string'] : '';
        $senderDto->post_index = isset($store->address['post_index']) ? $store->address['post_index'] : '';
        $senderDto->country_code = isset($store->address['country_code']) ? $store->address['country_code'] : '';
        $senderDto->region = isset($store->address['region']) ? $store->address['region'] : '';
        $senderDto->area = isset($store->address['area']) ? $store->address['area'] : '';
        $senderDto->city = isset($store->address['city']) ? $store->address['city'] : '';
        $senderDto->city_guid = isset($store->address['city_guid']) ? $store->address['city_guid'] : '';
        $senderDto->street = isset($store->address['street']) ? $store->address['street'] : '';
        $senderDto->house = isset($store->address['house']) ? $store->address['house'] : '';
        $senderDto->block = isset($store->address['block']) ? $store->address['block'] : '';
        $senderDto->flat = isset($store->address['flat']) ? $store->address['flat'] : '';
        $senderDto->company_name = $merchant->legal_name;
        $senderDto->contact_name = !is_null($store->storeContact()) ? $store->storeContact()[0]->name : '';
        $senderDto->email = !is_null($store->storeContact()) ? $store->storeContact()[0]->email : '';
        $senderDto->phone = !is_null($store->storeContact()) ? $store->storeContact()[0]->phone : '';

        $deliveryCargoDto = new DeliveryCargoDto();
        $courierCallInputDto->cargo = $deliveryCargoDto;
        $deliveryCargoDto->weight = $cargo->weight;
        $deliveryCargoDto->width = $cargo->width;
        $deliveryCargoDto->height = $cargo->height;
        $deliveryCargoDto->length = $cargo->length;
        $orderIds = [];
        foreach ($cargo->shipments as $shipment) {
            if ($shipment->delivery->xml_id) {
                $orderIds[] = $shipment->delivery->xml_id;
            }
        }
        $deliveryCargoDto->order_ids = $orderIds;

        /** @var CourierCallService $courierCallService */
        $courierCallService = resolve(CourierCallService::class);

        //Получаем доступные дни недели для отгрузки грузов курьерам службы доставки текущего груза
        /** @var Collection|StorePickupTimeDto[] $storePickupTimes */
        $storePickupTimes = collect();
        if ($store->storePickupTime()) {
            for ($day = 1; $day <= 7; $day++) {
                /** @var StorePickupTimeDto $pickupTimeDto */
                //Ищем время отгрузки с учетом службы доставки
                $pickupTimeDto = $store->storePickupTime()->filter(function (StorePickupTimeDto $item) use (
                    $day,
                    $cargo
                ) {
                    return $item->day == $day &&
                        $item->delivery_service == $cargo->delivery_service &&
                        ($item->pickup_time_code || ($item->pickup_time_start && $item->pickup_time_end));
                })->first();

                if (!is_null($pickupTimeDto)) {
                    $storePickupTimes->put($day, $pickupTimeDto);
                }
            }
        }

        $dayPlus = 0;
        $date = new \DateTime();
        while ($dayPlus <= 6) {
            //Получаем номер дня недели (1 - понедельник, ..., 7 - воскресенье)
            $dayOfWeek = $date->format('N');
            $date = $date->modify('+' . $dayPlus . 'day' . ($dayPlus > 1 ?  's': ''));
            $dayPlus++;
            if (!$storePickupTimes->has($dayOfWeek)) {
                continue;
            }

            $deliveryCargoDto->date = $date->format('d.m.Y');
            $deliveryCargoDto->time_code = $storePickupTimes[$dayOfWeek]->pickup_time_code;
            $deliveryCargoDto->time_start = $storePickupTimes[$dayOfWeek]->pickup_time_start;
            $deliveryCargoDto->time_end = $storePickupTimes[$dayOfWeek]->pickup_time_end;

            try {
                $courierCallOutputDto = $courierCallService->createCourierCall(
                    $cargo->delivery_service,
                    $courierCallInputDto
                );
                if ($courierCallOutputDto->success) {
                    $cargo->xml_id = $courierCallOutputDto->xml_id;
                    $cargo->status = CargoStatus::STATUS_REQUEST_SEND;

                    $cargo->save();
                    break;
                }
            } catch (\Exception $e) {
            }
        }
    }

    /**
     * Отменить груз (все отправления груза отвязываются от него!)
     * @param  int  $cargoId
     * @param  bool  $save
     * @return bool
     */
    public function cancelCargo(int $cargoId, bool $save = true): bool
    {
        /** @var Cargo $cargo */
        $cargo = Cargo::query()->where('id', $cargoId)->with('shipments')->first();
        if (is_null($cargo)) {
            return false;
        }

        $result = DB::transaction(function () use ($cargo, $save) {
            $cargo->status = CargoStatus::STATUS_CANCEL;
            if ($save) {
                $cargo->save();
            }

            if ($cargo->shipments->isNotEmpty()) {
                foreach ($cargo->shipments as $shipment) {
                    $shipment->cargo_id = null;
                    $shipment->save();
                }
            }

            return true;
        });

        if ($result) {
            $this->cancelCourierCall($cargo);

            return true;
        } else {
            return false;
        }
    }

    /**
     * Отменить заявку на вызов курьера для забора груза
     * @param  Cargo  $cargo
     */
    protected function cancelCourierCall(Cargo $cargo): void
    {
        if ($cargo->xml_id) {
            /** @var CourierCallService $courierCallService */
            $courierCallService = resolve(CourierCallService::class);
            $courierCallService->cancelCourierCall($cargo->delivery_service, $cargo->xml_id);
        }
    }

    public function updateDeliveryStatusFromDeliveryService(): void
    {
        $deliveries = Delivery::deliveriesAtWork();

        if ($deliveries->isNotEmpty()) {
            /** @var DeliveryOrderService $deliveryOrderService */
            $deliveryOrderService = resolve(DeliveryOrderService::class);

            $deliveriesByService = $deliveries->groupBy('delivery_service');
            foreach ($deliveriesByService as $deliveryServiceId => $items) {
                try {
                    /** @var Collection|Delivery[] $items */
                    $deliveryOrderStatusDtos = $deliveryOrderService->statusOrders($deliveryServiceId,
                        $items->pluck('xml_id')->all());
                    foreach ($deliveryOrderStatusDtos as $deliveryOrderStatusDto) {
                        if ($deliveries->has($deliveryOrderStatusDto->number)) {
                            $delivery = $deliveries[$deliveryOrderStatusDto->number];
                            if ($deliveryOrderStatusDto->success) {
                                $delivery->status = $deliveryOrderStatusDto->status;
                                $delivery->status_xml_id = $deliveryOrderStatusDto->status_xml_id;
                                $delivery->status_xml_id_at = new Carbon($deliveryOrderStatusDto->status_date);
                                $delivery->save();
                            }
                        }
                    }
                } catch (\Exception $e) {
                }
            }
        }
    }
}
