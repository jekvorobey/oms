<?php

namespace App\Observers\Delivery;

use App\Models\Delivery\ShipmentPackageItem;
use App\Models\History\History;
use App\Models\History\HistoryType;

/**
 * Class ShipmentPackageItemObserver
 * @package App\Observers\Delivery
 */
class ShipmentPackageItemObserver
{
    /**
     * Handle the package item "created" event.
     * @param ShipmentPackageItem $shipmentPackageItemItem
     * @return void
     */
    public function created(ShipmentPackageItem $shipmentPackageItem)
    {
        History::saveEvent(
            HistoryType::TYPE_CREATE,
            [
                $shipmentPackageItem->shipmentPackage->shipment->delivery->order,
                $shipmentPackageItem->shipmentPackage->shipment,
            ],
            $shipmentPackageItem
        );
    }

    /**
     * Handle the package item "updated" event.
     * @return void
     */
    public function updated(ShipmentPackageItem $shipmentPackageItem)
    {
        History::saveEvent(
            HistoryType::TYPE_UPDATE,
            [
                $shipmentPackageItem->shipmentPackage->shipment->delivery->order,
                $shipmentPackageItem->shipmentPackage->shipment,
            ],
            $shipmentPackageItem
        );
    }

    /**
     * Handle the package item "deleting" event.
     * @throws \Exception
     */
    public function deleting(ShipmentPackageItem $shipmentPackageItem)
    {
        History::saveEvent(
            HistoryType::TYPE_DELETE,
            [
                $shipmentPackageItem->shipmentPackage->shipment->delivery->order,
                $shipmentPackageItem->shipmentPackage->shipment,
            ],
            $shipmentPackageItem
        );
    }

    /**
     * Handle the package item "deleted" event.
     * @throws \Exception
     */
    public function deleted(ShipmentPackageItem $shipmentPackageItem)
    {
        $shipmentPackageItem->shipmentPackage->recalcWeight();
    }

    /**
     * Handle the package item "saved" event.
     * @throws \Exception
     */
    public function saved(ShipmentPackageItem $shipmentPackageItem)
    {
        if ($shipmentPackageItem->qty != $shipmentPackageItem->getOriginal('qty')) {
            $shipmentPackageItem->shipmentPackage->recalcWeight();
        }
    }
}
