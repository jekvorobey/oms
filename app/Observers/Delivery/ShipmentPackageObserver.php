<?php

namespace App\Observers\Delivery;

use App\Models\Delivery\ShipmentPackage;

/**
 * Class ShipmentPackageObserver
 * @package App\Observers\Delivery
 */
class ShipmentPackageObserver
{
    /**
     * Handle the shipment package "deleted" event.
     * @throws \Exception
     */
    public function deleted(ShipmentPackage $shipmentPackage)
    {
        $shipmentPackage->shipment->recalc();
    }

    /**
     * Handle the shipment package "saving" event.
     * @throws \Exception
     */
    public function saving(ShipmentPackage $shipmentPackage)
    {
        if ($shipmentPackage->wasChanged('wrapper_weight')) {
            $shipmentPackage->recalcWeight(false);
        }
    }

    /**
     * Handle the shipment package "saved" event.
     * @throws \Exception
     */
    public function saved(ShipmentPackage $shipmentPackage)
    {
        $needRecalc = false;

        foreach (['weight', 'width', 'height', 'length'] as $field) {
            if ($shipmentPackage->wasChanged($field)) {
                $needRecalc = true;
                break;
            }
        }
        if ($needRecalc) {
            $shipmentPackage->shipment->recalc();
        }
    }
}
