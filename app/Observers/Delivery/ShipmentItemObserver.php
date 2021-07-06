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
     * Handle the shipment item "created" event.
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
     * Handle the shipment item "updated" event.
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
     * Handle the shipment item "deleting" event.
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
     * Handle the shipment item "deleted" event.
     * @throws \Exception
     */
    public function deleted(ShipmentItem $shipmentItem)
    {
        $shipmentItem->shipment->costRecalc();
        $shipmentItem->shipment->recalc();
    }

    /**
     * Handle the shipment item "saved" event.
     * @throws \Exception
     */
    public function saved(ShipmentItem $shipmentItem)
    {
        $shipmentItem->shipment->costRecalc();
        $shipmentItem->shipment->recalc();
    }
}
