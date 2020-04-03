<?php

namespace App\Observers\Delivery;

use App\Models\Delivery\Cargo;
use App\Models\Delivery\CargoStatus;
use App\Models\Delivery\ShipmentStatus;
use App\Models\History\History;
use App\Models\History\HistoryType;

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
     * Handle the cargo "created" event.
     * @param  Cargo $cargo
     * @return void
     */
    public function created(Cargo $cargo)
    {
        History::saveEvent(HistoryType::TYPE_CREATE, $cargo, $cargo);
    }

    /**
     * Handle the cargo "updating" event.
     * @param  Cargo $cargo
     * @return bool
     */
    public function updating(Cargo $cargo): bool
    {
        if (!$this->checkHasShipments($cargo)) {
            return false;
        }

        return true;
    }

    /**
     * Handle the cargo "updated" event.
     * @param  Cargo $cargo
     * @return void
     */
    public function updated(Cargo $cargo)
    {
        History::saveEvent(HistoryType::TYPE_UPDATE, $cargo, $cargo);

        $this->setStatusToShipments($cargo);
    }

    /**
     * Handle the cargo "deleting" event.
     * @param  Cargo $cargo
     * @throws \Exception
     */
    public function deleting(Cargo $cargo)
    {
        History::saveEvent(HistoryType::TYPE_DELETE, $cargo, $cargo);
    }

    /**
     * Handle the order "saving" event.
     * @param  Cargo $cargo
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
     * @param Cargo $cargo
     * @return bool
     */
    protected function checkHasShipments(Cargo $cargo): bool
    {
        if ($cargo->status != $cargo->getOriginal('status') &&
            $cargo->status == CargoStatus::SHIPPED
        ) {
            $cargo->loadMissing('shipments');

            return $cargo->shipments->isNotEmpty();
        }

        return true;
    }

    /**
     * Установить дату изменения статуса груза
     * @param  Cargo $cargo
     */
    protected function setStatusAt(Cargo $cargo): void
    {
        if ($cargo->status != $cargo->getOriginal('status')) {
            $cargo->status_at = now();
        }
    }

    /**
     * Установить дату установки флага проблемного груза
     * @param  Cargo $cargo
     */
    protected function setProblemAt(Cargo $cargo): void
    {
        if ($cargo->is_problem != $cargo->getOriginal('is_problem')) {
            $cargo->is_problem_at = now();
        }
    }

    /**
     * Установить дату отмены груза
     * @param  Cargo $cargo
     */
    protected function setCanceledAt(Cargo $cargo): void
    {
        if ($cargo->is_canceled != $cargo->getOriginal('is_canceled')) {
            $cargo->is_canceled_at = now();
        }
    }

    /**
     * Установить статус груза всем его отправлениям
     * @param  Cargo $cargo
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
