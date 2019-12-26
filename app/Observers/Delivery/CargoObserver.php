<?php

namespace App\Observers\Delivery;

use App\Models\Delivery\Cargo;
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
}
