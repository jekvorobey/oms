<?php

namespace App\Services;

use App\Models\Basket\BasketItem;
use App\Models\Delivery\Shipment;
use App\Models\Delivery\ShipmentPackage;
use App\Models\Delivery\ShipmentPackageItem;
use Exception;
use Greensight\Message\Services\ServiceNotificationService\ServiceNotificationService;
use Greensight\Store\Services\PackageService\PackageService;
use Illuminate\Support\Facades\DB;

/**
 * Класс-бизнес логики по работе с сущностями коробок отправлений
 * Class ShipmentPackageService
 * @package App\Services
 */
class ShipmentPackageService
{
    protected DeliveryService $deliveryService;
    protected ShipmentService $shipmentService;
    protected ServiceNotificationService $notificationService;

    public function __construct()
    {
        $this->deliveryService = resolve(DeliveryService::class);
        $this->shipmentService = resolve(ShipmentService::class);
        $this->notificationService = app(ServiceNotificationService::class);
    }

    /**
     * Создать коробку отправления
     */
    public function createShipmentPackage(int $shipmentId, int $packageId): ?ShipmentPackage
    {
        $shipment = $this->shipmentService->getShipment($shipmentId);
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

            if ($basketItem->qty < $qty) {
                throw new DeliveryServiceInvalidConditions('Shipment package qty can\'t be more than basket item qty');
            }

            if (is_null($shipmentPackageItem)) {
                $shipmentPackageItem = new ShipmentPackageItem();
            }
            $shipmentPackageItem->updateOrCreate([
                'shipment_package_id' => $shipmentPackageId,
                'basket_item_id' => $basketItemId,
            ], ['qty' => $qty, 'set_by' => $setBy]);
        }

        return $ok;
    }

    /**
     * Проверить, что все товары отправления упакованы по коробкам
     */
    public function checkAllShipmentProductsPacked(Shipment $shipment): bool
    {
        $shipment->loadMissing('items.basketItem', 'packages.items');

        $shipmentItems = [];
        foreach ($shipment->items as $shipmentItem) {
            $shipmentItems[$shipmentItem->basket_item_id] = null;
            if ($shipmentItem->basketItem && !$shipmentItem->basketItem->isCanceled() && !$shipmentItem->basketItem->isReturned()) {
                $shipmentItems[$shipmentItem->basket_item_id] = $shipmentItem->basketItem->qty;
            }
        }

        foreach ($shipment->packages as $package) {
            foreach ($package->items as $packageItem) {
                $shipmentItems[$packageItem->basket_item_id] -= $packageItem->qty;
            }
        }

        return empty(array_filter($shipmentItems));
    }
}
