<?php

namespace App\Observers\Delivery;

use App\Models\Delivery\Cargo;
use App\Models\Delivery\CargoStatus;
use App\Models\Delivery\DeliveryStatus;
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
     * Автоматическая установка статуса для доставки, если все её отправления получили нужный статус
     */
    protected const STATUS_TO_DELIVERY = [
        ShipmentStatus::ASSEMBLING => DeliveryStatus::ASSEMBLING,
        ShipmentStatus::ASSEMBLED => DeliveryStatus::ASSEMBLED,
        ShipmentStatus::SHIPPED => DeliveryStatus::SHIPPED,
    ];

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

        $this->setStatusToDelivery($shipment);
        $this->setTakenStatusToCargo($shipment);
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
     * Handle the order "saving" event.
     * @param  Shipment $shipment
     * @return void
     */
    public function saving(Shipment $shipment)
    {
        $this->setStatusAt($shipment);
        $this->setPaymentStatusAt($shipment);
        $this->setProblemAt($shipment);
        $this->setCanceledAt($shipment);
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

            return $deliveryService->checkAllShipmentProductsPacked($shipment);
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
            $orderService->markAsProblem($shipment->delivery->order);
        }
    }
    
    /**
     * Пометить заказ как непроблемный, если все его отправления непроблемные
     * @param Shipment $shipment
     */
    protected function markOrderAsNonProblem(Shipment $shipment): void
    {
        if ($shipment->is_problem != $shipment->getOriginal('is_problem') && !$shipment->is_problem) {
            /** @var OrderService $orderService */
            $orderService = resolve(OrderService::class);
            $orderService->markAsNonProblem($shipment->delivery->order);
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
            $deliveryService->addShipment2Cargo($shipment);
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

    /**
     * Установить дату изменения статуса отправления
     * @param  Shipment $shipment
     */
    protected function setStatusAt(Shipment $shipment): void
    {
        if ($shipment->status != $shipment->getOriginal('status')) {
            $shipment->status_at = now();
        }
    }

    /**
     * Установить дату изменения статуса оплаты отправления
     * @param  Shipment $shipment
     */
    protected function setPaymentStatusAt(Shipment $shipment): void
    {
        if ($shipment->payment_status != $shipment->getOriginal('payment_status')) {
            $shipment->payment_status_at = now();
        }
    }

    /**
     * Установить дату установки флага проблемного отправления
     * @param  Shipment $shipment
     */
    protected function setProblemAt(Shipment $shipment): void
    {
        if ($shipment->is_problem != $shipment->getOriginal('is_problem')) {
            $shipment->is_problem_at = now();
        }
    }

    /**
     * Установить дату отмены отправления
     * @param  Shipment $shipment
     */
    protected function setCanceledAt(Shipment $shipment): void
    {
        if ($shipment->is_canceled != $shipment->getOriginal('is_canceled')) {
            $shipment->is_canceled_at = now();
        }
    }

    /**
     * Переводим в статус "Ожидает проверки АОЗ" из статуса "Оформлено",
     * если статус доставки "Ожидает проверки АОЗ"
     * @param  Shipment $shipment
     */
    protected function setAwaitingCheckStatus(Shipment $shipment): void
    {
        if ($shipment->status == ShipmentStatus::CREATED && $shipment->delivery->status == DeliveryStatus::AWAITING_CHECK) {
            $shipment->status = ShipmentStatus::AWAITING_CHECK;
        }
    }

    /**
     * Переводим в статус "Ожидает подтверждения Мерчантом" из статуса "Оформлено",
     * если статус доставки "Ожидает подтверждения Мерчантом"
     * @param  Shipment $shipment
     */
    protected function setAwaitingConfirmationStatus(Shipment $shipment): void
    {
        if ($shipment->status == ShipmentStatus::CREATED && $shipment->delivery->status == DeliveryStatus::AWAITING_CONFIRMATION) {
            $shipment->status = ShipmentStatus::AWAITING_CONFIRMATION;
        }
    }

    /**
     * Автоматическая установка статуса для доставки, если все её отправления получили нужный статус
     * @param  Shipment  $shipment
     */
    protected function setStatusToDelivery(Shipment $shipment): void
    {
        if (isset(self::STATUS_TO_DELIVERY[$shipment->status]) && $shipment->status != $shipment->getOriginal('status')) {
            $delivery = $shipment->delivery;
            if ($delivery->status == self::STATUS_TO_DELIVERY[$shipment->status]) {
                return;
            }

            $allShipmentsHasStatus = true;
            foreach ($delivery->shipments as $deliveryShipment) {
                if ($deliveryShipment->status < $shipment->status) {
                    $allShipmentsHasStatus = false;
                    break;
                }
            }

            if ($allShipmentsHasStatus) {
                $delivery->status = self::STATUS_TO_DELIVERY[$shipment->status];
                $delivery->save();
            }
        }
    }

    /**
     * Автоматическая установка статуса "Принят Логистическим Оператором" для груза,
     * если все его отправления получили статус "Принято Логистическим Оператором"
     * @param  Shipment  $shipment
     */
    protected function setTakenStatusToCargo(Shipment $shipment): void
    {
        if ($shipment->status == ShipmentStatus::ON_POINT_IN && $shipment->status != $shipment->getOriginal('status')) {
            $cargo = $shipment->cargo;
            if ($cargo->status == CargoStatus::TAKEN) {
                return;
            }

            $allShipmentsHasStatus = true;
            foreach ($cargo->shipments as $cargoShipment) {
                if ($cargoShipment->status < $shipment->status) {
                    $allShipmentsHasStatus = false;
                    break;
                }
            }

            if ($allShipmentsHasStatus) {
                $cargo->status = CargoStatus::TAKEN;
                $cargo->save();
            }
        }
    }

    /**
     * Переводим доставку в статус "Предзаказ: ожидаем поступления товара",
     * если статус отправления "Предзаказ: ожидаем поступления товара"
     * @param  Shipment $shipment
     */
    protected function setPreOrderStatusToDelivery(Shipment $shipment): void
    {
        if ($shipment->status == ShipmentStatus::PRE_ORDER) {
            $delivery = $shipment->delivery;
            $delivery->status = DeliveryStatus::PRE_ORDER;
            $delivery->save();
        }
    }
}
