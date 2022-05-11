<?php

namespace App\Observers\Delivery;

use App\Models\Delivery\ShipmentItem;
use Exception;

/**
 * Class ShipmentItemObserver
 * @package App\Observers\Delivery
 */
class ShipmentItemObserver
{
    /**
     * Handle the shipment item "deleted" event.
     * @throws Exception
     */
    public function deleted(ShipmentItem $shipmentItem)
    {
        $shipmentItem->shipment->costRecalc();
        $shipmentItem->shipment->recalc();
    }

    /**
     * Handle the shipment item "saved" event.
     * @throws Exception
     */
    public function saved(ShipmentItem $shipmentItem)
    {
        $shipmentItem->shipment->costRecalc();
        $shipmentItem->shipment->recalc();
    }
}
