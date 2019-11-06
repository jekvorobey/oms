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
     * Handle the order "created" event.
     * @param  ShipmentPackageItem $shipmentPackageItemItem
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
     * Handle the order "updated" event.
     * @param  ShipmentPackageItem $shipmentPackageItem
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
     * Handle the order "deleting" event.
     * @param  ShipmentPackageItem $shipmentPackageItem
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
     * Handle the order "deleted" event.
     * @param  ShipmentPackageItem $shipmentPackageItem
     * @throws \Exception
     */
    public function deleted(ShipmentPackageItem $shipmentPackageItem)
    {
        $shipmentPackageItem->shipmentPackage->recalcWeight();
    }
    
    /**
     * Handle the order "saved" event.
     * @param  ShipmentPackageItem $shipmentPackageItem
     * @throws \Exception
     */
    public function saved(ShipmentPackageItem $shipmentPackageItem)
    {
        if ($shipmentPackageItem->qty != $shipmentPackageItem->getOriginal('qty')) {
            $shipmentPackageItem->shipmentPackage->recalcWeight();
        }
    }
}
