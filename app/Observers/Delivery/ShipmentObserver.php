<?php

namespace App\Observers\Delivery;

use App\Models\Delivery\Cargo;
use App\Models\Delivery\Shipment;
use App\Models\Delivery\ShipmentStatus;
use App\Models\History\History;
use App\Models\History\HistoryType;
use App\Services\DeliveryService;
use App\Services\OrderService;
use Exception;

/**
 * Class ShipmentObserver
 * @package App\Observers\Delivery
 */
class ShipmentObserver
{
    /**
     * Handle the shipment "created" event.
     * @param  Shipment $shipment
     * @return void
     */
    public function created(Shipment $shipment)
    {
        History::saveEvent(HistoryType::TYPE_CREATE, [$shipment->delivery->order, $shipment], $shipment);
    }
    
    /**
     * Handle the shipment "updating" event.
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
     * Handle the shipment "updated" event.
     * @param  Shipment $shipment
     * @return void
     */
    public function updated(Shipment $shipment)
    {
        History::saveEvent(HistoryType::TYPE_UPDATE, [$shipment->delivery->order, $shipment], $shipment);
    }
    
    /**
     * Handle the shipment "deleting" event.
     * @param  Shipment $shipment
     * @throws Exception
     */
    public function deleting(Shipment $shipment)
    {
        History::saveEvent(HistoryType::TYPE_DELETE, [$shipment->delivery->order, $shipment], $shipment);
    
        foreach ($shipment->packages as $package) {
            $package->delete();
        }
    }
    
    /**
     * Handle the shipment "deleted" event.
     * @param  Shipment $shipment
     * @throws Exception
     */
    public function deleted(Shipment $shipment)
    {
        if ($shipment->cargo_id) {
            $shipment->cargo->recalc();
        }
        $shipment->delivery->recalc();
    }
    
    /**
     * Handle the shipment "saved" event.
     * @param  Shipment $shipment
     * @throws Exception
     */
    public function saved(Shipment $shipment)
    {
        $this->recalcCargoAndDeliveryOnSaved($shipment);
        $this->recalcCargosOnSaved($shipment);
        $this->markOrderAsProblem($shipment);
        $this->markOrderAsNonProblem($shipment);
        $this->upsertDeliveryOrder($shipment);
        $this->add2Cargo($shipment);
        $this->add2CargoHistory($shipment);
    }
    
    /**
     * Проверить, что все товары отправления упакованы по коробкам, если статус меняется на "Собрано"
     * @param Shipment $shipment
     * @return bool
     */
    protected function checkAllProductsPacked(Shipment $shipment): bool
    {
        if ($shipment->status != $shipment->getOriginal('status') &&
            $shipment->status == ShipmentStatus::ASSEMBLED
        ) {
            /** @var DeliveryService $deliveryService */
            $deliveryService = resolve(DeliveryService::class);

            return $deliveryService->checkAllShipmentProductsPacked($shipment->id);
        }
        
        return true;
    }
    
    /**
     * Пересчитать груз и доставку при сохранении отправления
     * @param Shipment $shipment
     */
    protected function recalcCargoAndDeliveryOnSaved(Shipment $shipment): void
    {
        $needRecalc = false;
        foreach (['weight', 'width', 'height', 'length'] as $field) {
            if ($shipment->getOriginal($field) != $shipment[$field]) {
                $needRecalc = true;
                break;
            }
        }
        
        if ($needRecalc) {
            if ($shipment->cargo_id) {
                $shipment->cargo->recalc();
            }
    
            $shipment->delivery->recalc();
        }
    }
    
    /**
     * Пересчитать старый и новый грузы при сохранении отправления
     * @param Shipment $shipment
     */
    protected function recalcCargosOnSaved(Shipment $shipment): void
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
        if ($shipment->is_problem != $shipment->getOriginal('is_problem') &&
            $shipment->is_problem) {
            /** @var OrderService $orderService */
            $orderService = resolve(OrderService::class);
            $orderService->markAsProblem($shipment->delivery->order_id);
        }
    }
    
    /**
     * Пометить заказ как непроблемный, если все его отправления непроблемные
     * @param Shipment $shipment
     */
    protected function markOrderAsNonProblem(Shipment $shipment): void
    {
        if ($shipment->status != $shipment->getOriginal('status') &&
            !$shipment->is_problem) {
            /** @var OrderService $orderService */
            $orderService = resolve(OrderService::class);
            $orderService->markAsNonProblem($shipment->delivery->order_id);
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
            in_array($shipment->status, [ShipmentStatus::ASSEMBLING, ShipmentStatus::ASSEMBLED])
        ) {
            $shipment->loadMissing('delivery.shipments');
            $delivery = $shipment->delivery;

            /** @var DeliveryService $deliveryService */
            $deliveryService = resolve(DeliveryService::class);
            $deliveryService->saveDeliveryOrder($delivery);
        }
    }
    
    /**
     * Добавить отправление в груз
     * @param Shipment $shipment
     */
    protected function add2Cargo(Shipment $shipment): void
    {
        try {
            /** @var DeliveryService $deliveryService */
            $deliveryService = resolve(DeliveryService::class);
            $deliveryService->addShipment2Cargo($shipment->id);
        } catch (Exception $e) {
        }
    }
    
    /**
     * Добавить информацию о добавлении/удалении отправления в/из груз/а
     * @param  Shipment  $shipment
     */
    protected function add2CargoHistory(Shipment $shipment): void
    {
        if ($shipment->cargo_id != $shipment->getOriginal('cargo_id')) {
            if ($shipment->getOriginal('cargo_id')) {
                History::saveEvent(HistoryType::TYPE_DELETE_LINK, Cargo::find($shipment->getOriginal('cargo_id')), $shipment);
            }
            
            if ($shipment->cargo_id) {
                History::saveEvent(HistoryType::TYPE_CREATE_LINK, Cargo::find($shipment->cargo_id), $shipment);
            }
        }
    }
}
