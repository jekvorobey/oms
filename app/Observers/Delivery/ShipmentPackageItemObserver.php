<?php

namespace App\Observers\Delivery;

use App\Models\Delivery\ShipmentPackageItem;

/**
 * Class ShipmentPackageItemObserver
 * @package App\Observers\Delivery
 */
class ShipmentPackageItemObserver
{
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
        if ($shipmentPackageItem->wasChanged('qty')) {
            $shipmentPackageItem->shipmentPackage->recalcWeight();
        }
    }
}
