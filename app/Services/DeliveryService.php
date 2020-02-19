<?php

namespace App\Services;

use App\Models\Basket\BasketItem;
use App\Models\Delivery\Delivery;
use App\Models\Delivery\Shipment;
use App\Models\Delivery\ShipmentPackage;
use App\Models\Delivery\ShipmentPackageItem;
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
    /** @var array|Delivery[] - кэш доставок с id в качестве ключа */
    public static $deliveriesCached = [];
    /** @var array|Shipment[] - кэш отправлений с id в качестве ключа */
    public static $shipmentsCached = [];

    /**
     * Получить объект отправления по его id
     * @param  int  $deliveryId
     * @return Delivery|null
     */
    public function getDelivery(int $deliveryId): ?Delivery
    {
        if (!isset(static::$deliveriesCached[$deliveryId])) {
            static::$deliveriesCached[$deliveryId] = Delivery::find($deliveryId);
        }

        return static::$deliveriesCached[$deliveryId];
    }

    /**
     * @param  Delivery  $delivery
     */
    public function addDelivery2Cache(Delivery $delivery): void
    {
        static::$deliveriesCached[$delivery->id] = $delivery;
    }

    /**
     * Получить объект отправления по его id
     * @param  int  $shipmentId
     * @return Shipment|null
     */
    public function getShipment(int $shipmentId): ?Shipment
    {
        if (!isset(static::$shipmentsCached[$shipmentId])) {
            static::$shipmentsCached[$shipmentId] = Shipment::find($shipmentId);
        }

        return static::$shipmentsCached[$shipmentId];
    }

    /**
     * @param  Shipment  $delivery
     */
    public function addShipment2Cache(Shipment $delivery): void
    {
        static::$shipmentsCached[$delivery->id] = $delivery;
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
        if ($shipment) {
            return null;
        }

        /** @var PackageService $packageService */
        $packageService = resolve(PackageService::class);
        $package = $packageService->package($packageId);

        $shipmentPackage = new ShipmentPackage();
        $shipmentPackage->shipment_id = $shipment->id;
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
}
