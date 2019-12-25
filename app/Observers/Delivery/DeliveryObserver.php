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
     * Handle the delivery "created" event.
     * @param  Delivery $delivery
     * @return void
     */
    public function created(Delivery $delivery)
    {
        History::saveEvent(HistoryType::TYPE_CREATE, $delivery->order, $delivery);
    }
    
    /**
     * Handle the delivery "updated" event.
     * @param  Delivery $delivery
     * @return void
     */
    public function updated(Delivery $delivery)
    {
        History::saveEvent(HistoryType::TYPE_UPDATE, $delivery->order, $delivery);
    }
    
    /**
     * Handle the delivery "deleting" event.
     * @param  Delivery $delivery
     * @throws \Exception
     */
    public function deleting(Delivery $delivery)
    {
        History::saveEvent(HistoryType::TYPE_DELETE, $delivery->order, $delivery);
    }
    
    /**
     * Handle the delivery "saved" event.
     * @param  Delivery $delivery
     */
    public function saving(Delivery $delivery)
    {
        $this->setStatusAt($delivery);
    }
    
    /**
     * Установить дату изменения статуса доставки
     * @param  Delivery  $delivery
     */
    protected function setStatusAt(Delivery $delivery): void
    {
        if ($delivery->status != $delivery->getOriginal('status')) {
            $delivery->status_at = now();
        }
    }
}
