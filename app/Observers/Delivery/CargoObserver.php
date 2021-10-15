<?php

namespace App\Observers\Delivery;

use App\Models\Delivery\Cargo;
use App\Models\Delivery\CargoStatus;
use App\Models\Delivery\ShipmentStatus;

/**
 * Class CargoObserver
 * @package App\Observers\Delivery
 */
class CargoObserver
{
    /**
     * Автоматическая установка статуса груза для всех его отправлений
     */
    protected const STATUS_TO_SHIPMENTS = [
        CargoStatus::SHIPPED => ShipmentStatus::SHIPPED,
        CargoStatus::TAKEN => ShipmentStatus::ON_POINT_IN,
    ];

    /**
     * Handle the cargo "updating" event.
     */
    public function updating(Cargo $cargo): bool
    {
        return $this->checkHasShipments($cargo);
    }

    /**
     * Handle the cargo "updated" event.
     * @return void
     */
    public function updated(Cargo $cargo)
    {
        $this->setStatusToShipments($cargo);
    }

    /**
     * Handle the order "saving" event.
     * @return void
     */
    public function saving(Cargo $cargo)
    {
        $this->setStatusAt($cargo);
        $this->setProblemAt($cargo);
        $this->setCanceledAt($cargo);
    }

    /**
     * Проверить, что в грузе есть отправления, если статус меняется на "Груз передан курьеру"
     */
    protected function checkHasShipments(Cargo $cargo): bool
    {
        if (
            $cargo->status != $cargo->getOriginal('status') &&
            $cargo->status == CargoStatus::SHIPPED
        ) {
            $cargo->loadMissing('shipments');

            return $cargo->shipments->isNotEmpty();
        }

        return true;
    }

    /**
     * Установить дату изменения статуса груза
     */
    protected function setStatusAt(Cargo $cargo): void
    {
        if ($cargo->status != $cargo->getOriginal('status')) {
            $cargo->status_at = now();
        }
    }

    /**
     * Установить дату установки флага проблемного груза
     */
    protected function setProblemAt(Cargo $cargo): void
    {
        if ($cargo->is_problem != $cargo->getOriginal('is_problem')) {
            $cargo->is_problem_at = now();
        }
    }

    /**
     * Установить дату отмены груза
     */
    protected function setCanceledAt(Cargo $cargo): void
    {
        if ($cargo->is_canceled != $cargo->getOriginal('is_canceled')) {
            $cargo->is_canceled_at = now();
        }
    }

    /**
     * Установить статус груза всем его отправлениям
     */
    protected function setStatusToShipments(Cargo $cargo): void
    {
        if (isset(self::STATUS_TO_SHIPMENTS[$cargo->status]) && $cargo->status != $cargo->getOriginal('status')) {
            $cargo->loadMissing('shipments');
            foreach ($cargo->shipments as $shipment) {
                $shipment->status = self::STATUS_TO_SHIPMENTS[$cargo->status];
                $shipment->save();
            }
        }
    }
}
