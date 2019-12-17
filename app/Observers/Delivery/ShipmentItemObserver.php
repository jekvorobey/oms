<?php

namespace App\Observers\Delivery;

use App\Models\Delivery\ShipmentItem;
use App\Models\History\History;
use App\Models\History\HistoryType;

/**
 * Class ShipmentItemObserver
 * @package App\Observers\Delivery
 */
class ShipmentItemObserver
{
    /**
     * Handle the order "created" event.
     * @param  ShipmentItem $shipmentItem
     * @return void
     */
    public function created(ShipmentItem $shipmentItem)
    {
        History::saveEvent(
            HistoryType::TYPE_CREATE,
            [
                $shipmentItem->shipment->delivery->order,
                $shipmentItem->shipment,
            ],
            $shipmentItem
        );
    }
    
    /**
     * Handle the order "updated" event.
     * @param  ShipmentItem $shipmentItem
     * @return void
     */
    public function updated(ShipmentItem $shipmentItem)
    {
        History::saveEvent(
            HistoryType::TYPE_UPDATE,
            [
                $shipmentItem->shipment->delivery->order,
                $shipmentItem->shipment,
            ],
            $shipmentItem
        );
    }
    
    /**
     * Handle the order "deleting" event.
     * @param  ShipmentItem $shipmentItem
     * @throws \Exception
     */
    public function deleting(ShipmentItem $shipmentItem)
    {
        History::saveEvent(
            HistoryType::TYPE_DELETE,
            [
                $shipmentItem->shipment->delivery->order,
                $shipmentItem->shipment,
            ],
            $shipmentItem
        );
    }
    
    /**
     * Handle the order "deleted" event.
     * @param  ShipmentItem $shipmentItem
     * @throws \Exception
     */
    public function deleted(ShipmentItem $shipmentItem)
    {
        $shipmentItem->shipment->costRecalc();
        $shipmentItem->shipment->recalc();
    }
    
    /**
     * Handle the order "saved" event.
     * @param  ShipmentItem $shipmentItem
     * @throws \Exception
     */
    public function saved(ShipmentItem $shipmentItem)
    {
        $shipmentItem->shipment->costRecalc();
        $shipmentItem->shipment->recalc();
    }
}
