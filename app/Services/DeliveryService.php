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
use Greensight\Store\Services\PackageService\PackageService;

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
}
