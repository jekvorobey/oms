<?php

namespace App\Observers\Delivery;

use App\Models\Delivery\Delivery;
use App\Models\History\History;
use App\Models\History\HistoryType;

/**
 * Class DeliveryObserver
 * @package App\Observers\Delivery
 */
class DeliveryObserver
{
    /**
     * Handle the order "created" event.
     * @param  Delivery $delivery
     * @return void
     */
    public function created(Delivery $delivery)
    {
        History::saveEvent(HistoryType::TYPE_CREATE, $delivery->order, $delivery);
    }
    
    /**
     * Handle the order "updated" event.
     * @param  Delivery $delivery
     * @return void
     */
    public function updated(Delivery $delivery)
    {
        History::saveEvent(HistoryType::TYPE_UPDATE, $delivery->order, $delivery);
    }
    
    /**
     * Handle the order "deleting" event.
     * @param  Delivery $delivery
     * @throws \Exception
     */
    public function deleting(Delivery $delivery)
    {
        History::saveEvent(HistoryType::TYPE_DELETE, $delivery->order, $delivery);
    }
}
