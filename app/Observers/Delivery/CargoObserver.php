<?php

namespace App\Observers\Delivery;

use App\Models\Delivery\Cargo;
use App\Models\Delivery\CargoStatus;
use App\Models\History\History;
use App\Models\History\HistoryType;

/**
 * Class CargoObserver
 * @package App\Observers\Delivery
 */
class CargoObserver
{
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
     * Проверить, что в грузе есть отправления, если статус меняется на "Груз передан курьеру"
     * @param Cargo $cargo
     * @return bool
     */
    protected function checkHasShipments(Cargo $cargo): bool
    {
        if ($cargo->status != $cargo->getOriginal('status') &&
            $cargo->status == CargoStatus::STATUS_SHIPPED
        ) {
            $cargo->load('shipments');

            return $cargo->shipments->isNotEmpty();
        }

        return true;
    }
}
