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
        $this->setPaymentStatusAt($delivery);
        $this->setProblemAt($delivery);
        $this->setCanceledAt($delivery);
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

    /**
     * Установить дату изменения статуса оплаты доставки
     * @param  Delivery $delivery
     */
    protected function setPaymentStatusAt(Delivery $delivery): void
    {
        if ($delivery->payment_status != $delivery->getOriginal('payment_status')) {
            $delivery->payment_status_at = now();
        }
    }

    /**
     * Установить дату установки флага проблемной доставки
     * @param  Delivery $delivery
     */
    protected function setProblemAt(Delivery $delivery): void
    {
        if ($delivery->is_problem != $delivery->getOriginal('is_problem')) {
            $delivery->is_problem_at = now();
        }
    }

    /**
     * Установить дату отмены доставки
     * @param  Delivery $delivery
     */
    protected function setCanceledAt(Delivery $delivery): void
    {
        if ($delivery->is_canceled != $delivery->getOriginal('is_canceled')) {
            $delivery->is_canceled_at = now();
        }
    }
}
