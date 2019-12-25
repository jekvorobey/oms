<?php

namespace App\Observers\Delivery;

use App\Models\Delivery\ShipmentPackage;
use App\Models\History\History;
use App\Models\History\HistoryType;

/**
 * Class ShipmentPackageObserver
 * @package App\Observers\Delivery
 */
class ShipmentPackageObserver
{
    /**
     * Handle the shipment package "created" event.
     * @param  ShipmentPackage $shipmentPackage
     * @return void
     */
    public function created(ShipmentPackage $shipmentPackage)
    {
        History::saveEvent(
            HistoryType::TYPE_CREATE,
            [
                $shipmentPackage->shipment->delivery->order,
                $shipmentPackage->shipment
            ],
            $shipmentPackage
        );
    }
    
    /**
     * Handle the shipment package "updated" event.
     * @param  ShipmentPackage $shipmentPackage
     * @return void
     */
    public function updated(ShipmentPackage $shipmentPackage)
    {
        History::saveEvent(
            HistoryType::TYPE_UPDATE,
            [
                $shipmentPackage->shipment->delivery->order,
                $shipmentPackage->shipment
            ],
            $shipmentPackage
        );
    }
    
    /**
     * Handle the shipment package "deleting" event.
     * @param  ShipmentPackage $shipmentPackage
     * @throws \Exception
     */
    public function deleting(ShipmentPackage $shipmentPackage)
    {
        History::saveEvent(
            HistoryType::TYPE_DELETE,
            [
                $shipmentPackage->shipment->delivery->order,
                $shipmentPackage->shipment
            ],
            $shipmentPackage
        );
    }
    
    /**
     * Handle the shipment package "deleted" event.
     * @param  ShipmentPackage $shipmentPackage
     * @throws \Exception
     */
    public function deleted(ShipmentPackage $shipmentPackage)
    {
        $shipmentPackage->shipment->recalc();
    }
    
    /**
     * Handle the shipment package "saving" event.
     * @param  ShipmentPackage $shipmentPackage
     * @throws \Exception
     */
    public function saving(ShipmentPackage $shipmentPackage)
    {
        if ($shipmentPackage->wrapper_weight != $shipmentPackage->getOriginal('wrapper_weight')) {
            $shipmentPackage->recalcWeight(false);
        }
    }
    
    /**
     * Handle the shipment package "saved" event.
     * @param  ShipmentPackage $shipmentPackage
     * @throws \Exception
     */
    public function saved(ShipmentPackage $shipmentPackage)
    {
        $needRecalc = false;
        
        foreach (['weight', 'width', 'height', 'length'] as $field) {
            if ($shipmentPackage->getOriginal($field) != $shipmentPackage[$field]) {
                $needRecalc = true;
                break;
            }
        }
        if ($needRecalc) {
            $shipmentPackage->shipment->recalc();
        }
    }
}
