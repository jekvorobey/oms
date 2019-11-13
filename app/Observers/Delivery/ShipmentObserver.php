<?php

namespace App\Observers\Delivery;

use App\Models\Delivery\Cargo;
use App\Models\Delivery\Shipment;
use App\Models\Delivery\ShipmentStatus;
use App\Models\History\History;
use App\Models\History\HistoryType;

/**
 * Class ShipmentObserver
 * @package App\Observers\Delivery
 */
class ShipmentObserver
{
    /**
     * Handle the order "created" event.
     * @param  Shipment $shipment
     * @return void
     */
    public function created(Shipment $shipment)
    {
        History::saveEvent(HistoryType::TYPE_CREATE, [$shipment->delivery->order, $shipment], $shipment);
    }
    
    /**
     * Handle the order "updating" event.
     * @param  Shipment $shipment
     * @return bool
     */
    public function updating(Shipment $shipment): bool
    {
        //Проверяем, что все товары отправления упакованы по коробкам, если статус меняется на "Собрано"
        if ($shipment->status != $shipment->getOriginal('status') &&
            $shipment->status == ShipmentStatus::STATUS_ASSEMBLED
        ) {
            $shipment->load('items.basketItem', 'packages.items');
    
            $shipmentItems = [];
            foreach ($shipment->items as $shipmentItem) {
                $shipmentItems[$shipmentItem->basket_item_id] = $shipmentItem->basketItem->qty;
            }
            
            foreach ($shipment->packages as $package) {
                foreach ($package->items as $packageItem) {
                    $shipmentItems[$packageItem->basket_item_id] -= $packageItem->qty;
                }
            }
            
            return empty(array_filter($shipmentItems));
        }
        
        return true;
    }
    
    /**
     * Handle the order "updated" event.
     * @param  Shipment $shipment
     * @return void
     */
    public function updated(Shipment $shipment)
    {
        History::saveEvent(HistoryType::TYPE_UPDATE, [$shipment->delivery->order, $shipment], $shipment);
    }
    
    /**
     * Handle the order "deleting" event.
     * @param  Shipment $shipment
     * @throws \Exception
     */
    public function deleting(Shipment $shipment)
    {
        History::saveEvent(HistoryType::TYPE_DELETE, [$shipment->delivery->order, $shipment], $shipment);
    
        foreach ($shipment->packages as $package) {
            $package->delete();
        }
    }
    
    /**
     * Handle the order "deleted" event.
     * @param  Shipment $shipment
     * @throws \Exception
     */
    public function deleted(Shipment $shipment)
    {
        if ($shipment->cargo_id) {
            /** @var Cargo $newCargo */
            $newCargo = Cargo::find($shipment->cargo_id);
            if ($newCargo) {
                $newCargo->recalc();
            }
        }
    }
    
    /**
     * Handle the order "saved" event.
     * @param  Shipment $shipment
     * @throws \Exception
     */
    public function saved(Shipment $shipment)
    {
        $oldCargoId = $shipment->getOriginal('cargo_id');
        if ($oldCargoId != $shipment->cargo_id) {
            if ($oldCargoId) {
                /** @var Cargo $oldCargo */
                $oldCargo = Cargo::find($oldCargoId);
                if ($oldCargo) {
                    $oldCargo->recalc();
                }
            }
            if ($shipment->cargo_id) {
                /** @var Cargo $newCargo */
                $newCargo = Cargo::find($shipment->cargo_id);
                if ($newCargo) {
                    $newCargo->recalc();
                }
            }
        }
        
        if ($shipment->status != $shipment->getOriginal('status') &&
            in_array($shipment->status, [ShipmentStatus::STATUS_ASSEMBLING_PROBLEM, ShipmentStatus::STATUS_TIMEOUT])) {
            $order = $shipment->delivery->order;
            $order->is_problem = true;
            $order->save();
        }
        
        if ($shipment->status != $shipment->getOriginal('status') &&
            $shipment->getOriginal('status') == ShipmentStatus::STATUS_ASSEMBLING_PROBLEM) {
            $order = $shipment->delivery->order;
            $isAllShipmentsOk = true;
            foreach ($order->deliveries as $delivery) {
                foreach ($delivery->shipments as $shipment) {
                    if (in_array($shipment->status, [
                        ShipmentStatus::STATUS_ASSEMBLING_PROBLEM, ShipmentStatus::STATUS_TIMEOUT
                    ])) {
                        $isAllShipmentsOk = false;
                        break 2;
                    }
                }
            }
    
            $order->is_problem = !$isAllShipmentsOk;
            $order->save();
        }
    }
}
