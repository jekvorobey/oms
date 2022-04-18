<?php

namespace App\Services;

use App\Models\Basket\BasketItem;
use App\Models\Delivery\Cargo;
use App\Models\Delivery\CargoStatus;
use App\Models\Delivery\Delivery;
use App\Models\Delivery\DeliveryStatus;
use App\Models\Delivery\Shipment;
use App\Models\Delivery\ShipmentPackage;
use App\Models\Delivery\ShipmentPackageItem;
use App\Models\Delivery\ShipmentStatus;
use App\Models\Payment\PaymentStatus;
use App\Services\Dto\In\OrderReturn\OrderReturnDtoBuilder;
use Cms\Core\CmsException;
use Cms\Dto\OptionDto;
use Cms\Services\OptionService\OptionService;
use Exception;
use Greensight\CommonMsa\Dto\AbstractDto;
use Greensight\CommonMsa\Dto\RoleDto;
use Greensight\CommonMsa\Services\IbtService\IbtService;
use Greensight\Logistics\Dto\CourierCall\CourierCallInput\CourierCallInputDto;
use Greensight\Logistics\Dto\CourierCall\CourierCallInput\DeliveryCargoDto;
use Greensight\Logistics\Dto\CourierCall\CourierCallInput\SenderDto;
use Greensight\Logistics\Dto\Lists\DeliveryService as LogisticsDeliveryService;
use Greensight\Logistics\Dto\Lists\PointDto;
use Greensight\Logistics\Dto\Lists\ShipmentMethod;
use Greensight\Logistics\Dto\Order\CdekDeliveryOrderReceiptDto;
use Greensight\Logistics\Dto\Order\DeliveryOrderBarcodesDto;
use Greensight\Logistics\Dto\Order\DeliveryOrderInput;
use Greensight\Logistics\Dto\Order\DeliveryOrderInput\DeliveryOrderCostDto;
use Greensight\Logistics\Dto\Order\DeliveryOrderInput\DeliveryOrderDto;
use Greensight\Logistics\Dto\Order\DeliveryOrderInput\DeliveryOrderInputDto;
use Greensight\Logistics\Dto\Order\DeliveryOrderInput\DeliveryOrderItemDto;
use Greensight\Logistics\Dto\Order\DeliveryOrderInput\DeliveryOrderPlaceDto;
use Greensight\Logistics\Dto\Order\DeliveryOrderInput\RecipientDto;
use Greensight\Logistics\Services\CourierCallService\CourierCallService;
use Greensight\Logistics\Services\DeliveryOrderService\DeliveryOrderService;
use Greensight\Logistics\Services\ListsService\ListsService;
use Greensight\Message\Services\ServiceNotificationService\ServiceNotificationService;
use Greensight\Store\Dto\StorePickupTimeDto;
use Greensight\Store\Services\PackageService\PackageService;
use Greensight\Store\Services\StoreService\StoreService;
use Illuminate\Database\Eloquent\Model;
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
     */
    public function getDelivery(int $deliveryId): ?Delivery
    {
        return Delivery::find($deliveryId);
    }

    /**
     * Получить объект отправления по его id
     */
    public function getShipment(int $shipmentId): ?Shipment
    {
        return Shipment::find($shipmentId);
    }

    /**
     * Получить объект груза по его id
     */
    public function getCargo(int $cargoId): ?Cargo
    {
        return Cargo::find($cargoId);
    }

    /**
     * Получить объект отправления по его идентификатору заказа на доставку в службе доставки
     */
    public function getDeliveryByXmlId(int $xmlId)
    {
        return Delivery::query()->where('xml_id', $xmlId)->first();
    }

    /**
     * Создать коробку отправления
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

            if ($basketItem->isCanceled()) {
                throw new DeliveryServiceInvalidConditions('Shipment item is canceled');
            }

            if ($basketItem->qty < $qty) {
                throw new DeliveryServiceInvalidConditions('Shipment package qty can\'t be more than basket item qty');
            } else {
                if (is_null($shipmentPackageItem)) {
                    $shipmentPackageItem = new ShipmentPackageItem();
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
     * Отменить элемент (товар с одного склада одного мерчанта) отправления
     * @throws Exception
     */
    public function cancelShipmentItem(
        int $shipmentId,
        int $basketItemId,
        float $qtyToCancel,
        int $cancelBy,
        int $returnReasonId
    ): bool {
        /** @var Shipment $shipment */
        $shipment = Shipment::find($shipmentId);
        if (!$qtyToCancel && $shipment->basketItems->where('id', $basketItemId)->isNotEmpty()) {
            throw new DeliveryServiceInvalidConditions('Shipment item not found');
        }
        if ($shipment->status > DeliveryStatus::ASSEMBLING) {
            throw new DeliveryServiceInvalidConditions('Shipment status is exceeded');
        }
        /** @var BasketItem $basketItem */
        $basketItem = BasketItem::find($basketItemId);
        if ($basketItem->isCanceled()) {
            throw new DeliveryServiceInvalidConditions('Basket item is already canceled');
        }
        if ($basketItem->qty < $qtyToCancel) {
            throw new DeliveryServiceInvalidConditions('Cancel qty can\'t be more than basket item qty');
        }
        $newQty = $basketItem->qty - $qtyToCancel;
        $oldPrice = $basketItem->price;
        $basketItem->update(
            [
                'qty' => $newQty,
                'qty_canceled' => $basketItem->qty_canceled + $qtyToCancel,
                'is_canceled' => $newQty == 0,
                'canceled_by' => $cancelBy,
                'return_reason_id' => $returnReasonId,
            ]
        );
        $basketItemReturnDto = (new OrderReturnDtoBuilder())
            ->buildFromCancelBasketItem($basketItem->basket->order, $basketItem, $qtyToCancel, $oldPrice);
        /** @var OrderReturnService $orderReturnService */
        $orderReturnService = resolve(OrderReturnService::class);
        $orderReturn = $orderReturnService->create($basketItemReturnDto);

        return (bool) $orderReturn;
    }

    /**
     * Проверить, что все товары отправления упакованы по коробкам
     */
    public function checkAllShipmentProductsPacked(Shipment $shipment): bool
    {
        $shipment->loadMissing('items.basketItem', 'packages.items');

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
     * @throws Exception
     */
    public function addShipment2Cargo(Shipment $shipment): void
    {
        if ($shipment->is_canceled) {
            throw new DeliveryServiceInvalidConditions('Отправление отменено');
        }
        if ($shipment->status != ShipmentStatus::ASSEMBLED) {
            throw new DeliveryServiceInvalidConditions('Отправление не собрано');
        }
        if ($shipment->cargo_id) {
            throw new DeliveryServiceInvalidConditions('Отправление уже добавлено в груз');
        }
        if (!$shipment->delivery->xml_id) {
            throw new DeliveryServiceInvalidConditions('Задание на доставку не создано');
        }

        $deliveryService = $this->getZeroMileShipmentDeliveryServiceId($shipment);

        $cargoQuery = Cargo::query()
            ->select('id')
            ->where('merchant_id', $shipment->merchant_id)
            ->where('store_id', $shipment->store_id)
            ->where('delivery_service', $deliveryService)
            ->where('status', CargoStatus::CREATED)
            ->where('is_canceled', false)
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
            $cargo->status = CargoStatus::CREATED;
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
     * @throws Exception
     */
    public function createCourierCall(Cargo $cargo): void
    {
        if ($cargo->status != CargoStatus::CREATED) {
            throw new DeliveryServiceInvalidConditions('Груз не в статусе "Создан"');
        }
        if ($cargo->xml_id) {
            throw new DeliveryServiceInvalidConditions(
                'Для груза уже создана заявка на вызов курьера с номером "' . $cargo->xml_id . '"'
            );
        }
        if ($cargo->shipments->isEmpty()) {
            throw new DeliveryServiceInvalidConditions('Груз не содержит отправлений');
        }

        /** @var StoreService $storeService */
        $storeService = resolve(StoreService::class);
        $storeQuery = $storeService->newQuery()
            ->include('storeContact', 'storePickupTime');
        $store = $storeService->store($cargo->store_id, $storeQuery);
        if (is_null($store)) {
            $cargo->error_xml_id = 'Не найден склад с id="' . $cargo->store_id . '" для груза';
            $cargo->save();
            return;
        }

        /** @var MerchantService $merchantService */
        $merchantService = resolve(MerchantService::class);
        $merchant = $merchantService->merchant($store->merchant_id);

        $courierCallInputDto = new CourierCallInputDto();

        $senderDto = new SenderDto();
        $senderDto->address_string = $store->address['address_string'] ?? '';
        $senderDto->post_index = $store->address['post_index'] ?? '';
        $senderDto->country_code = $store->address['country_code'] ?? '';
        $senderDto->region = $store->address['region'] ?? '';
        $senderDto->area = $store->address['area'] ?? '';
        $senderDto->city = $store->address['city'] ?? '';
        $senderDto->city_guid = $store->address['city_guid'] ?? '';
        $senderDto->region_guid = $store->address['region_guid'] ?? '';
        $senderDto->street = $store->address['street'] ?? '';
        $senderDto->house = $store->address['house'] ?? '';
        $senderDto->block = $store->address['block'] ?? '';
        $senderDto->flat = $store->address['flat'] ?? '';
        $senderDto->company_name = $merchant->legal_name;
        $senderDto->contact_name = !is_null($store->storeContact()) ? $store->storeContact()[0]->name : '';
        $senderDto->email = !is_null($store->storeContact()) ? $store->storeContact()[0]->email : '';
        $senderDto->phone = !is_null($store->storeContact()) ? phoneNumberFormat($store->storeContact()[0]->phone) : '';
        $courierCallInputDto->sender = $senderDto;

        if ($store->cdek_address && (!empty($store->cdek_address['address_string']) || !empty($store->cdek_address['code']))) {
            $cdekSenderDto = new SenderDto();
            $cdekSenderDto->address_string = $store->cdek_address['address_string'] ?? '';
            $cdekSenderDto->cdek_city_code = $store->cdek_address['code'] ?? '';
            $cdekSenderDto->post_index = $store->cdek_address['post_index'] ?? '';
            $cdekSenderDto->country_code = $store->cdek_address['country_code'] ?? '';
            $cdekSenderDto->region = $store->cdek_address['region'] ?? '';
            $cdekSenderDto->area = $store->cdek_address['area'] ?? '';
            $cdekSenderDto->city = $store->cdek_address['city'] ?? '';
            $cdekSenderDto->city_guid = $store->cdek_address['city_guid'] ?? '';
            $cdekSenderDto->street = $store->cdek_address['street'] ?? '';
            $cdekSenderDto->house = $store->cdek_address['house'] ?? '';
            $cdekSenderDto->block = $store->cdek_address['block'] ?? '';
            $cdekSenderDto->flat = $store->cdek_address['flat'] ?? '';
            $courierCallInputDto->cdekSender = $cdekSenderDto;
        }

        $deliveryCargoDto = new DeliveryCargoDto();

        $deliveryCargoDto->weight = $cargo->weight;
        $deliveryCargoDto->width = $cargo->width;
        $deliveryCargoDto->height = $cargo->height;
        $deliveryCargoDto->length = $cargo->length;
        $orderIds = [];
        foreach ($cargo->shipments as $shipment) {
            $orderIds[] = $shipment->delivery->xml_id ?: 0;
        }
        $deliveryCargoDto->order_ids = $orderIds;

        /** @var CourierCallService $courierCallService */
        $courierCallService = resolve(CourierCallService::class);

        //Получаем доступные дни недели для отгрузки грузов курьерам службы доставки текущего груза
        /** @var Collection|StorePickupTimeDto[] $storePickupTimes */
        $storePickupTimes = collect();
        if ($store->storePickupTime()) {
            for ($day = 1; $day <= 7; $day++) {
                //Ищем время отгрузки с учетом службы доставки
                /** @var StorePickupTimeDto $pickupTimeDto */
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
        // @todo: fix get timezone by store
        $timezone = new \DateTimeZone('Europe/Moscow');
        $dateNow = new \DateTimeImmutable('now', $timezone);
        while ($dayPlus <= 6) {
            $date = $dateNow->modify('+' . $dayPlus . ' day' . ($dayPlus > 1 ? 's' : ''));
            //Получаем номер дня недели (1 - понедельник, ..., 7 - воскресенье)
            $dayOfWeek = $date->format('N');
            $dayPlus++;
            if (!$storePickupTimes->has($dayOfWeek)) {
                $cargo->error_xml_id = 'Возможно у склада не указан график отгрузки';
                continue;
            }

            $deliveryDateTo = new \DateTimeImmutable($date->format('Y-m-d') . 'T' . $storePickupTimes[$dayOfWeek]->pickup_time_end, $timezone);
            if ($dateNow > $deliveryDateTo) {
                continue;
            }

            $deliveryCargoDto->date = $date->format('d.m.Y');
            $deliveryCargoDto->time_code = $storePickupTimes[$dayOfWeek]->pickup_time_code;
            $deliveryCargoDto->time_start = $storePickupTimes[$dayOfWeek]->pickup_time_start;
            $deliveryCargoDto->time_end = $storePickupTimes[$dayOfWeek]->pickup_time_end;

            $courierCallInputDto->cargo = $deliveryCargoDto;
            try {
                $courierCallOutputDto = $courierCallService->createCourierCall(
                    $cargo->delivery_service,
                    $courierCallInputDto
                );
                if ($courierCallOutputDto->success) {
                    $cargo->xml_id = $courierCallOutputDto->xml_id;
                    $cargo->error_xml_id = $courierCallOutputDto->special_courier_call_status;
                    $cargo->intake_date = $date;
                    $cargo->intake_time_from = $deliveryCargoDto->time_start;
                    $cargo->intake_time_to = $deliveryCargoDto->time_end;
                    break;
                } else {
                    $cargo->error_xml_id = $courierCallOutputDto->message;
                }
            } catch (\Throwable $e) {
                $cargo->error_xml_id = $e->getMessage();
                report($e);
            }
        }
        if ($cargo->error_xml_id) {
            $cargo->save();
            throw new Exception($cargo->error_xml_id);
        }

        $cargo->save();
    }

    /**
     * Отменить груз (все отправления груза отвязываются от него!)
     * @throws Exception
     */
    public function cancelCargo(Cargo $cargo): bool
    {
        if ($cargo->status >= CargoStatus::SHIPPED) {
            throw new DeliveryServiceInvalidConditions(
                'Груз, начиная со статуса "Передан Логистическому Оператору", нельзя отменить'
            );
        }

        $result = DB::transaction(function () use ($cargo) {
            $cargo->is_canceled = true;
            $cargo->save();

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
     */
    public function cancelCourierCall(Cargo $cargo): void
    {
        if ($cargo->xml_id) {
            /** @var CourierCallService $courierCallService */
            $courierCallService = resolve(CourierCallService::class);
            $courierCallService
                ->cancelCourierCall($cargo->delivery_service, $cargo->xml_id);

            $cargo->xml_id = '';
            $cargo->cdek_intake_number = null;
            $cargo->error_xml_id = '';
            $cargo->save();
        }
    }

    /**
     * Проверить ошибки в заявке на вызов курьера во внешнем сервисе
     */
    public function checkExternalStatus(Cargo $cargo): void
    {
        if ($cargo->xml_id) {
            /** @var CourierCallService $courierCallService */
            $courierCallService = resolve(CourierCallService::class);
            $status = $courierCallService
                ->checkExternalStatus($cargo->delivery_service, $cargo->xml_id);

            $cargo->error_xml_id = $status->error;
            $cargo->cdek_intake_number = $status->intake_number;
            $cargo->updated_at = Carbon::now();
            $cargo->save();
        }
    }

    /**
     * Сохранить (создать или обновить) заказ на доставку с службе доставке
     * @throws Exception
     */
    public function saveDeliveryOrder(Delivery $delivery): void
    {
        $delivery->loadMissing('shipments');
        /**
         * Проверяем, что товары по все отправлениям доставки собраны
         */
        $cntShipments = 0;
        foreach ($delivery->shipments as $shipment) {
            if ($shipment->is_canceled) {
                continue;
            }

            $validShipmentStatuses = [
                ShipmentStatus::ASSEMBLED,
            ];

            if (!in_array($shipment->status, $validShipmentStatuses)) {
                throw new DeliveryServiceInvalidConditions(
                    'Не все отправления доставки подтверждены/собраны мерчантами'
                );
            }

            $cntShipments++;
        }

        if (!$cntShipments) {
            throw new DeliveryServiceInvalidConditions(
                'Не все отправления доставки подтверждены/собраны мерчантами'
            );
        }

        $deliveryOrderInputDto = $this->formDeliveryOrder($delivery);
        /** @var DeliveryOrderService $deliveryOrderService */
        $deliveryOrderService = resolve(DeliveryOrderService::class);
        try {
            if (!$delivery->xml_id) {
                $deliveryOrderOutputDto = $deliveryOrderService->createOrder(
                    $delivery->delivery_service,
                    $deliveryOrderInputDto
                );
                if ($deliveryOrderOutputDto->success && $deliveryOrderOutputDto->xml_id) {
                    if ($deliveryOrderOutputDto->tracknumber) {
                        $delivery->tracknumber = $deliveryOrderOutputDto->tracknumber;
                    }
                    if ($deliveryOrderOutputDto->barcode) {
                        $delivery->barcode = $deliveryOrderOutputDto->barcode;
                    }
                    $delivery->error_xml_id = '';
                } else {
                    $delivery->error_xml_id = $deliveryOrderOutputDto->message;
                }
                if ($deliveryOrderOutputDto->xml_id) {
                    $delivery->xml_id = $deliveryOrderOutputDto->xml_id;
                }
                $delivery->save();
                foreach ($delivery->shipments as $shipment) {
                    if (!$shipment->cargo_id) {
                        $shipment->updated_at = now();
                        $shipment->save();
                    }
                }
            } else {
                $deliveryOrderOutputDto = $deliveryOrderService->updateOrder(
                    $delivery->delivery_service,
                    $delivery->xml_id,
                    $deliveryOrderInputDto
                );
                if ($deliveryOrderOutputDto->success) {
                    $delivery->error_xml_id = '';
                } else {
                    $delivery->error_xml_id = $deliveryOrderOutputDto->message;
                }
                $delivery->save();
            }

            /**
             * Указываем информация о кодах мест (коробок) в службе доставки
             */
            if ($deliveryOrderOutputDto->success && $deliveryOrderOutputDto->places->isNotEmpty()) {
                foreach ($deliveryOrderOutputDto->places as $place) {
                    foreach ($delivery->shipments as $shipment) {
                        foreach ($shipment->packages as $package) {
                            if ($place->code == $package->id || $place->code == $shipment->number) {
                                $package->xml_id = $place->code_xml_id;
                                $package->save();
                                break 2;
                            }
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            $delivery->error_xml_id = $e->getMessage();
            $delivery->save();
            report($e);
        }
    }

    /**
     * Сформировать заказ на доставку
     * @throws CmsException
     */
    protected function formDeliveryOrder(Delivery $delivery): DeliveryOrderInputDto
    {
        $delivery->loadMissing(['shipments.packages.items.basketItem']);
        $deliveryOrderInputDto = new DeliveryOrderInputDto();

        //Информация об получателе заказа
        $recipientDto = new RecipientDto((array) $delivery->delivery_address);
        $deliveryOrderInputDto->recipient = $recipientDto;

        //Информация о заказе
        $deliveryOrderDto = new DeliveryOrderDto();
        $deliveryOrderInputDto->order = $deliveryOrderDto;
        $deliveryOrderDto->number = $delivery->getDeliveryServiceNumber();
        $deliveryOrderDto->tracknumber = $delivery->tracknumber;
        $deliveryOrderDto->barcode = $delivery->barcode;
        $deliveryOrderDto->height = $delivery->height;
        $deliveryOrderDto->length = $delivery->length;
        $deliveryOrderDto->width = $delivery->width;
        $deliveryOrderDto->weight = $delivery->weight;
        $deliveryOrderDto->shipment_method = ShipmentMethod::METHOD_DS_COURIER;
        $deliveryOrderDto->delivery_method = $delivery->delivery_method;
        $deliveryOrderDto->tariff_id = $delivery->tariff_id;
        $deliveryOrderDto->delivery_date = $delivery->delivery_at->format(AbstractDto::DATE_FORMAT);
        $deliveryOrderDto->delivery_time_start = $delivery->delivery_time_start;
        $deliveryOrderDto->delivery_time_end = $delivery->delivery_time_end;
        $deliveryOrderDto->delivery_time_code = $delivery->delivery_time_code;
        $deliveryOrderDto->point_out_id = $delivery->point_id;
        $deliveryOrderDto->description = $recipientDto->comment;

        //Информация о стоимости заказа
        $deliveryOrderCostDto = new DeliveryOrderCostDto();
        $deliveryOrderInputDto->cost = $deliveryOrderCostDto;
        /**
         * Когда будет постоплата, указать стоимость доставки в поле ниже.
         * Уточнить, как рассчитывается стоимость доставки для одного заказа на доставку.
         * Наверное берется стоимость доставки для всего заказа и делится на кол-во доставок:
         * round($delivery->order->delivery_price / $delivery->order->deliveries->count(), 2)
         */
        $deliveryOrderCostDto->delivery_cost = 0;

        $deliveryOrderCostDto->cod_cost = 0;
        /** @var Shipment $shipment */
        foreach ($delivery->shipments as $shipment) {
            if ($shipment->is_canceled) {
                continue;
            }
            $deliveryOrderCostDto->cod_cost += $shipment->basketItems->sum('cost');
        }
        if ($delivery->isPostPaid()) {
            $deliveryOrderCostDto->delivery_cost_pay = round($delivery->order->delivery_price / $delivery->order->deliveries->count(), 2);
            $deliveryOrderCostDto->is_delivery_payed_by_recipient = true;
        } else {
            $deliveryOrderCostDto->delivery_cost_pay = 0;
            $deliveryOrderCostDto->is_delivery_payed_by_recipient = false;
        }
        $deliveryOrderCostDto->assessed_cost = $deliveryOrderCostDto->cod_cost;

        //Информация об отправителе заказа
        if ($delivery->shipments->count() == 1) {
            /**
             * Если в доставке одно отправление, то в качестве данных отправителя указываем данные мерчанта
             */
            /** @var StoreService $storeService */
            $storeService = resolve(StoreService::class);
            /** @var MerchantService $merchantService */
            $merchantService = resolve(MerchantService::class);
            $shipment = $delivery->shipments[0];
            $store = $storeService->store($shipment->store_id, $storeService->newQuery()->include('storeContact'));
            $merchant = $merchantService->merchant($shipment->merchant_id);

            $storeAddress = $store->address;
            $storeAddress['street'] = $storeAddress['street'] ?: '-'; //у cdek и b2cpl улица обязательна
            $senderDto = new DeliveryOrderInput\SenderDto($storeAddress);
            $deliveryOrderInputDto->sender = $senderDto;
            // если есть доп адрес для сдэка, то его тоже передаем
            $cdekSenderAddress = $store->cdek_address;
            if ($cdekSenderAddress && !empty($cdekSenderAddress['address_string'])) {
                $cdekSenderAddress['street'] = $cdekSenderAddress['street'] ?: '-';
                $deliveryOrderInputDto->cdekSender = $cdekSenderAddress;
            }

            $senderDto->is_seller = true;
            $senderDto->company_name = $merchant->legal_name;
            $senderDto->store_id = $shipment->store_id;
            $senderDto->inn = $merchant->inn;
            $storeContact = $store->storeContact[0];
            $senderDto->contact_name = $storeContact->name;
            $senderDto->email = $storeContact->email;
            $senderDto->phone = phoneNumberFormat($storeContact->phone);
            $senderDto->cdek_city_code = $cdekSenderAddress['code'] ?? null;
        } else {
            /**
             * Иначе указываем данные маркетплейса
             */
            /** @var OptionService $optionService */
            $optionService = resolve(OptionService::class);
            /** @var IbtService $ibtService */
            $ibtService = resolve(IbtService::class);
            //todo Переделать получение адреса склада iBT из OptionService
            $centralStoreAddress = $ibtService->getCentralStoreAddress();
            $senderDto = new DeliveryOrderInput\SenderDto($centralStoreAddress);
            $deliveryOrderInputDto->sender = $senderDto;
            $marketplaceData = $optionService->get([
                OptionDto::KEY_ORGANIZATION_CARD_FULL_NAME,
                OptionDto::KEY_ORGANIZATION_CARD_LOGISTICS_MANAGER_LAST_NAME,
                OptionDto::KEY_ORGANIZATION_CARD_LOGISTICS_MANAGER_FIRST_NAME,
                OptionDto::KEY_ORGANIZATION_CARD_LOGISTICS_MANAGER_MIDDLE_NAME,
                OptionDto::KEY_ORGANIZATION_CARD_LOGISTICS_MANAGER_EMAIL,
                OptionDto::KEY_ORGANIZATION_CARD_LOGISTICS_MANAGER_PHONE,
                OptionDto::KEY_ORGANIZATION_CARD_FACT_ADDRESS,
            ]);
            $senderDto->address_string = $marketplaceData[OptionDto::KEY_ORGANIZATION_CARD_FACT_ADDRESS];
            $senderDto->company_name = $marketplaceData[OptionDto::KEY_ORGANIZATION_CARD_FULL_NAME];
            $senderDto->contact_name = join(' ', [
                $marketplaceData[OptionDto::KEY_ORGANIZATION_CARD_LOGISTICS_MANAGER_LAST_NAME],
                $marketplaceData[OptionDto::KEY_ORGANIZATION_CARD_LOGISTICS_MANAGER_FIRST_NAME],
                $marketplaceData[OptionDto::KEY_ORGANIZATION_CARD_LOGISTICS_MANAGER_MIDDLE_NAME],
            ]);
            $senderDto->email = $marketplaceData[OptionDto::KEY_ORGANIZATION_CARD_LOGISTICS_MANAGER_EMAIL];
            $senderDto->phone = $marketplaceData[OptionDto::KEY_ORGANIZATION_CARD_LOGISTICS_MANAGER_PHONE];
        }
        //Для самовывоза указываем адрес ПВЗ
        if (!$delivery->delivery_address && $delivery->point_id) {
            /** @var ListsService $listsService */
            $listsService = resolve(ListsService::class);
            $pointQuery = $listsService->newQuery()
                ->setFilter('id', $delivery->point_id)
                ->addFields(PointDto::entity(), 'address', 'city_guid', 'cdek_city_code');
            /** @var PointDto|null $pointDto */
            $pointDto = $listsService->points($pointQuery)->first();
            if ($pointDto) {
                $recipientDto->post_index = $pointDto->address['post_index'] ?? '';
                $recipientDto->region = $pointDto->address['region'] ?? '';
                $recipientDto->area = $pointDto->address['area'] ?? '';
                $recipientDto->city = $pointDto->address['city'] ?? '';
                $recipientDto->city_guid = $pointDto->city_guid;
                $recipientDto->cdek_city_code = $pointDto->cdek_city_code;
                $recipientDto->street = $pointDto->address['street'] ?: '-'; //у cdek и b2cpl улица обязательна
                $recipientDto->house = $pointDto->address['house'] ?? '';
                $recipientDto->block = $pointDto->address['block'] ?? '';
                $recipientDto->flat = $pointDto->address['flat'] ?? '';
            }
        }

        $recipientDto->address_string = implode(', ', array_filter([
            $recipientDto->post_index,
            $recipientDto->region != $recipientDto->city ? $recipientDto->region : '',
            $recipientDto->area,
            $recipientDto->city,
            $recipientDto->street,
            $recipientDto->house,
            $recipientDto->block,
            $recipientDto->flat,
        ]));
        $recipientDto->street = $recipientDto->street ?: 'нет'; //у cdek и b2cpl улица обязательна
        $recipientDto->contact_name = $delivery->receiver_name;
        $recipientDto->email = $delivery->receiver_email;
        $recipientDto->phone = phoneNumberFormat($delivery->receiver_phone);

        //Информация о местах (коробках) заказа
        $places = collect();
        $deliveryOrderInputDto->places = $places;
        $packageNumber = 1;
        foreach ($delivery->shipments as $shipment) {
            if ($shipment->is_canceled) {
                continue;
            }

            if ($shipment->packages && $shipment->packages->isNotEmpty()) {
                foreach ($shipment->packages as $package) {
                    $deliveryOrderPlaceDto = new DeliveryOrderPlaceDto();
                    $places->push($deliveryOrderPlaceDto);
                    $deliveryOrderPlaceDto->number = $packageNumber++;
                    $deliveryOrderPlaceDto->code = $package->id;
                    $deliveryOrderPlaceDto->width = (int) ceil($package->width);
                    $deliveryOrderPlaceDto->height = (int) ceil($package->height);
                    $deliveryOrderPlaceDto->length = (int) ceil($package->length);
                    $deliveryOrderPlaceDto->weight = (int) ceil($package->weight);

                    $items = collect();
                    $deliveryOrderPlaceDto->items = $items;
                    foreach ($package->items as $item) {
                        $basketItem = $item->basketItem;
                        $deliveryOrderItemDto = new DeliveryOrderItemDto();
                        $items->push($deliveryOrderItemDto);
                        $deliveryOrderItemDto->articul = $basketItem->offer_id; //todo Добавить сохранение артикула товара в корзине
                        $deliveryOrderItemDto->name = $basketItem->name;
                        $deliveryOrderItemDto->quantity = (float) $item->qty;
                        $deliveryOrderItemDto->height = isset($basketItem->product['height']) ? (int) ceil($basketItem->product['height']) : 0;
                        $deliveryOrderItemDto->width = isset($basketItem->product['width']) ? (int) ceil($basketItem->product['width']) : 0;
                        $deliveryOrderItemDto->length = isset($basketItem->product['length']) ? (int) ceil($basketItem->product['length']) : 0;
                        $deliveryOrderItemDto->weight = isset($basketItem->product['weight']) ? (int) ceil($basketItem->product['weight']) : 0;
                        $deliveryOrderItemDto->cost = round($item->qty > 0 ? $basketItem->price / $item->qty : 0, 2);
                        if ($delivery->isPostPaid()) {
                            $deliveryOrderItemDto->price = $item->qty > 0 ? $basketItem->price / $item->qty : 0;
                        } else {
                            $deliveryOrderItemDto->price = 0;
                        }
                        $deliveryOrderItemDto->assessed_cost = $deliveryOrderItemDto->cost;
                    }
                }
            } else {
                $deliveryOrderPlaceDto = new DeliveryOrderPlaceDto();
                $places->push($deliveryOrderPlaceDto);
                $deliveryOrderPlaceDto->number = $packageNumber++;
                $deliveryOrderPlaceDto->code = $shipment->number;
                $deliveryOrderPlaceDto->width = (int) ceil($shipment->width);
                $deliveryOrderPlaceDto->height = (int) ceil($shipment->height);
                $deliveryOrderPlaceDto->length = (int) ceil($shipment->length);
                $deliveryOrderPlaceDto->weight = (int) ceil($shipment->weight);

                $items = collect();
                $deliveryOrderPlaceDto->items = $items;
                foreach ($shipment->items as $item) {
                    $basketItem = $item->basketItem;
                    $deliveryOrderItemDto = new DeliveryOrderItemDto();
                    $items->push($deliveryOrderItemDto);
                    $deliveryOrderItemDto->articul = $basketItem->offer_id; //todo Добавить сохранение артикула товара в корзине
                    $deliveryOrderItemDto->name = $basketItem->name;
                    $deliveryOrderItemDto->quantity = (float) $basketItem->qty;
                    $deliveryOrderItemDto->height = isset($basketItem->product['height']) ? (int) ceil($basketItem->product['height']) : 0;
                    $deliveryOrderItemDto->width = isset($basketItem->product['width']) ? (int) ceil($basketItem->product['width']) : 0;
                    $deliveryOrderItemDto->length = isset($basketItem->product['length']) ? (int) ceil($basketItem->product['length']) : 0;
                    $deliveryOrderItemDto->weight = isset($basketItem->product['weight']) ? (int) ceil($basketItem->product['weight']) : 0;
                    $deliveryOrderItemDto->cost = round($basketItem->qty > 0 ? $basketItem->cost / $basketItem->qty : 0, 2);
                    if ($delivery->isPostPaid()) {
                        $deliveryOrderItemDto->price = $item->qty > 0 ? $basketItem->price / $item->qty : 0;
                    } else {
                        $deliveryOrderItemDto->price = 0;
                    }
                    $deliveryOrderItemDto->assessed_cost = $deliveryOrderItemDto->cost;
                }
            }
        }

        return $deliveryOrderInputDto;
    }

    /**
     * Получить от служб доставок и обновить статусы заказов на доставку
     */
    public function updateDeliveryStatusFromDeliveryService(): void
    {
        $deliveries = Delivery::deliveriesInDelivery()->keyBy(fn(Delivery $delivery) => $delivery->getDeliveryServiceNumber());
        $deliveries->load('order');

        if ($deliveries->isNotEmpty()) {
            /** @var DeliveryOrderService $deliveryOrderService */
            $deliveryOrderService = resolve(DeliveryOrderService::class);

            $deliveriesByService = $deliveries->groupBy('delivery_service');
            /** @var Collection|Delivery[] $items */
            foreach ($deliveriesByService as $deliveryServiceId => $items) {
                try {
                    $deliveryOrderStatusDtos = $deliveryOrderService->statusOrders(
                        $deliveryServiceId,
                        $items->pluck('xml_id')->all()
                    );
                } catch (\Throwable $e) {
                    report($e);
                    continue;
                }

                foreach ($deliveryOrderStatusDtos as $deliveryOrderStatusDto) {
                    if (!$deliveries->has($deliveryOrderStatusDto->number)) {
                        continue;
                    }

                    $delivery = $deliveries[$deliveryOrderStatusDto->number];

                    if ($deliveryOrderStatusDto->success) {
                        try {
                            if ($deliveryOrderStatusDto->status && $delivery->status != $deliveryOrderStatusDto->status) {
                                $delivery->status = $deliveryOrderStatusDto->status;

                                if ($delivery->isPostPaid()) {
                                    if ($delivery->status === DeliveryStatus::DONE) {
                                        $delivery->payment_status = PaymentStatus::PAID;
                                    } elseif (in_array($delivery->status, [DeliveryStatus::CANCELLATION_EXPECTED, DeliveryStatus::RETURNED])) {
                                        $delivery->payment_status = PaymentStatus::TIMEOUT;
                                    }
                                }
                            }

                            $delivery->setStatusXmlId(
                                $deliveryOrderStatusDto->status_xml_id,
                                new Carbon($deliveryOrderStatusDto->status_date)
                            );

                            $delivery->save();
                        } catch (\Throwable $e) {
                            logger()->error("Error when updating status of Delivery #{$delivery->id} ({$delivery->xml_id})");
                            report($e);
                            continue;
                        }
                    }
                }
            }
        }
    }

    /**
     * Получить от сдэк статус и обновить статус заказа на доставку
     * @param Model|Delivery $delivery
     * @param array $data
     */
    public function updateDeliveryStatus($delivery, array $data): void
    {
        $delivery->setStatusXmlId(
            $data['statusCode'],
            new Carbon($data['status_date'])
        );
        $delivery->status = $data['status'];
        $delivery->save();
    }

    /**
     * Получить id службы доставки на нулевой миле для отправления.
     * Сначала проверяется, не указана ли служба доставки у самого отправления:
     * если указана, то возвращается она, иначе берется служба доставки у доставки, в которую входит отправление
     */
    public function getZeroMileShipmentDeliveryServiceId(Shipment $shipment): int
    {
        return $shipment->delivery_service_zero_mile ?: $shipment->delivery->delivery_service;
    }

    /**
     * Получить файл со штрихкодами коробок для заказа на доставку
     */
    public function getShipmentBarcodes(Shipment $shipment): ?DeliveryOrderBarcodesDto
    {
        $delivery = $shipment->delivery;

        if (!$delivery->xml_id) {
            return null;
        }
        if ($shipment->status < ShipmentStatus::ASSEMBLED) {
            return null;
        }

        try {
            /** @var DeliveryOrderService $deliveryOrderService */
            $deliveryOrderService = resolve(DeliveryOrderService::class);
            return $deliveryOrderService->barcodesOrder(
                $delivery->delivery_service,
                $delivery->xml_id,
                array_filter($shipment->packages->pluck('xml_id')->toArray())
            );
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }

    /**
     * Получить квитанцию cdek для заказа на доставку
     * @return DeliveryOrderBarcodesDto|null
     */
    public function getShipmentCdekReceipt(Shipment $shipment): ?CdekDeliveryOrderReceiptDto
    {
        $delivery = $shipment->delivery;

        if (!$delivery->xml_id) {
            return null;
        }
        if ($shipment->status < ShipmentStatus::ASSEMBLED) {
            return null;
        }

        try {
            /** @var DeliveryOrderService $deliveryOrderService */
            $deliveryOrderService = resolve(DeliveryOrderService::class);
            return $deliveryOrderService->cdekReceiptOrder($delivery->delivery_service, $delivery->xml_id);
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }

    /**
     * Пометить отправление как проблемное
     */
    public function markAsProblemShipment(Shipment $shipment, string $comment = ''): bool
    {
        $shipment->is_problem = true;
        $shipment->assembly_problem_comment = $comment;

        return $shipment->save();
    }

    /**
     * Пометить отправление как непроблемное
     */
    public function markAsNonProblemShipment(Shipment $shipment): bool
    {
        $shipment->is_problem = false;

        return $shipment->save();
    }

    /**
     * Отменить отправление
     * @throws Exception
     */
    public function cancelShipment(Shipment $shipment, ?int $orderReturnReasonId = null): bool
    {
        if ($shipment->status >= ShipmentStatus::DONE) {
            throw new DeliveryServiceInvalidConditions(
                'Отправление, начиная со статуса "Доставлено получателю", нельзя отменить'
            );
        }

        $shipment->return_reason_id ??= $orderReturnReasonId;

        $shipment->is_canceled = true;
        $shipment->cargo_id = null;

        if (!$shipment->save()) {
            return false;
        }

        $orderReturnDto = (new OrderReturnDtoBuilder())->buildFromShipment($shipment);

        if ($orderReturnDto) {
            /** @var OrderReturnService $orderReturnService */
            $orderReturnService = resolve(OrderReturnService::class);
            $orderReturnService->create($orderReturnDto);
        }

        if ($shipment->status > ShipmentStatus::CREATED) {
            $attributes = [
                'SHIPMENT_NUMBER' => $shipment->number,
                'LINK_ORDER' => sprintf('%s/orders/%d', config('app.admin_host'), $shipment->delivery->order_id),
            ];

            /** @var ServiceNotificationService $notificationService */
            $notificationService = resolve(ServiceNotificationService::class);
            $notificationService->sendByRole(RoleDto::ROLE_LOGISTIC, 'logistotpravlenie_otmeneno', $attributes);
        }

        return true;
    }

    /**
     * Отменить доставку
     * @throws Exception
     */
    public function cancelDelivery(Delivery $delivery, ?int $orderReturnReasonId = null): bool
    {
        if ($delivery->status >= DeliveryStatus::DONE) {
            throw new DeliveryServiceInvalidConditions(
                'Доставку, начиная со статуса "Доставлена получателю", нельзя отменить'
            );
        }

        $delivery->is_canceled = true;

        $delivery->return_reason_id ??= $orderReturnReasonId;

        if (!$delivery->save()) {
            return false;
        }

        rescue(fn() => $this->cancelDeliveryOrder($delivery));

        return true;
    }

    /**
     * Отменить заказ на доставку у службы доставки (ЛО)
     */
    public function cancelDeliveryOrder(Delivery $delivery): void
    {
        if ($delivery->xml_id) {
            /** @var DeliveryOrderService $deliveryOrderService */
            $deliveryOrderService = resolve(DeliveryOrderService::class);

            // IBT-621: не удалять xml_id при отмене доставки
            // $delivery->xml_id = '';
            // $delivery->save();

            if ($delivery->delivery_service === LogisticsDeliveryService::SERVICE_CDEK && $delivery->status >= DeliveryStatus::ASSEMBLED) {
                return;
            }

            $deliveryOrderService->cancelOrder($delivery->delivery_service, $delivery->xml_id);
        }
    }

    /**
     * Пометить доставку как проблемную
     */
    public function markAsProblem(Delivery $delivery): bool
    {
        $delivery->is_problem = true;

        return $delivery->save();
    }

    /**
     * Отменить флаг проблемности у доставки, если все отправления непроблемные
     */
    public function markAsNonProblem(Delivery $delivery): bool
    {
        $isAllShipmentsOk = true;
        foreach ($delivery->shipments as $shipment) {
            if ($shipment->is_problem) {
                $isAllShipmentsOk = false;
                break;
            }
        }
        $delivery->is_problem = !$isAllShipmentsOk;

        return $delivery->save();
    }
}
