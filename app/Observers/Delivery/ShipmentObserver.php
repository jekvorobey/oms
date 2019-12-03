<?php

namespace App\Observers\Delivery;

use App\Models\Delivery\Cargo;
use App\Models\Delivery\Delivery;
use App\Models\Delivery\Shipment;
use App\Models\Delivery\ShipmentStatus;
use App\Models\History\History;
use App\Models\History\HistoryType;
use Greensight\Logistics\Dto\Order\DeliveryOrderInput\DeliveryOrderDto;
use Greensight\Logistics\Dto\Order\DeliveryOrderInput\DeliveryOrderInputDto;
use Greensight\Logistics\Services\DeliveryOrderService\DeliveryOrderService;

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
        if (!$this->checkAllProductsPacked($shipment)) {
            return false;
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
        $this->recalcCargoOnDelete($shipment);
    }
    
    /**
     * Handle the order "saved" event.
     * @param  Shipment $shipment
     * @throws \Exception
     */
    public function saved(Shipment $shipment)
    {
        $this->recalcCargoOnSaved($shipment);
        $this->markOrderAsProblem($shipment);
        $this->markOrderAsNonProblem($shipment);
    }
    
    /**
     * Проверить, что все товары отправления упакованы по коробкам, если статус меняется на "Собрано"
     * @param Shipment $shipment
     * @return bool
     */
    protected function checkAllProductsPacked(Shipment $shipment): bool
    {
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
     * Пересчитать груз при удалении отправления
     * @param Shipment $shipment
     */
    protected function recalcCargoOnDelete(Shipment $shipment): void
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
     * Пересчитать груз при сохранении отправления
     * @param Shipment $shipment
     */
    protected function recalcCargoOnSaved(Shipment $shipment): void
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
    }
    
    /**
     * Пометить заказ как проблемный в случае проблемного отправления
     * @param Shipment $shipment
     */
    protected function markOrderAsProblem(Shipment $shipment): void
    {
        if ($shipment->status != $shipment->getOriginal('status') &&
            in_array($shipment->status, [ShipmentStatus::STATUS_ASSEMBLING_PROBLEM, ShipmentStatus::STATUS_TIMEOUT])) {
            $order = $shipment->delivery->order;
            $order->is_problem = true;
            $order->save();
        }
    }
    
    /**
     * Пометить заказ как непроблемный, если все его отправления непроблемные
     * @param Shipment $shipment
     */
    protected function markOrderAsNonProblem(Shipment $shipment): void
    {
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
    
    /**
     * Создать/обновить заказ на доставку
     * Создание заказа на доставку происходит когда все отправления доставки получают статус "Все товары отправления в наличии"
     * Обновление заказа на доставку происходит когда отправление доставки получает статус "Собрано"
     * @param Shipment $shipment
     */
    protected function upsertDeliveryOrder(Shipment $shipment): void
    {
        if ($shipment->status != $shipment->getOriginal('status') &&
            $shipment->getOriginal('status') == ShipmentStatus::STATUS_ALL_PRODUCTS_AVAILABLE) {
            $shipment->load('delivery.shipments');
            $delivery = $shipment->delivery;
            
            foreach ($delivery->shipments as $deliveryShipment) {
                if (!in_array($deliveryShipment->status, [
                    ShipmentStatus::STATUS_ALL_PRODUCTS_AVAILABLE,
                    ShipmentStatus::STATUS_ASSEMBLED,
                ])) {
                    return;
                }
            }
            
            $deliveryOrderInputDto = $this->formDeliveryOrder($delivery);
            /** @var DeliveryOrderService $deliveryOrderService */
            $deliveryOrderService = resolve(DeliveryOrderService::class);
            if (!$delivery->xml_id) {
                $deliveryOrderService->createOrder($delivery->delivery_service, $deliveryOrderInputDto);
            } else {
                $deliveryOrderService->updateOrder(
                    $delivery->delivery_service,
                    $delivery->xml_id,
                    $deliveryOrderInputDto
                );
            }
        }
    }
    
    /**
     * Сформировать заказ на доставку
     * @param Delivery $delivery
     * @return DeliveryOrderInputDto
     */
    protected function formDeliveryOrder(Delivery $delivery): DeliveryOrderInputDto
    {
        $delivery->load(['order', 'shipments.packages.items.basketItem']);
        $deliveryOrderInputDto = new DeliveryOrderInputDto();
        
        $deliveryOrderDto = new DeliveryOrderDto();
        $deliveryOrderInputDto->order = $deliveryOrderDto;
        $deliveryOrderDto->number = $delivery->number;
        $deliveryOrderDto->height = $delivery->height;
        $deliveryOrderDto->length = $delivery->length;
        $deliveryOrderDto->width = $delivery->width;
        $deliveryOrderDto->pickup_type = 1; //todo указано жестко, т.к. не понятно кто будет доставлять заказ от мерчанта до РЦ на нулевой миле
        $deliveryOrderDto->delivery_method = $delivery->delivery_method;
    }
}
