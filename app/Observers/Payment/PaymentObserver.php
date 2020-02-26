<?php

namespace App\Observers\Payment;

use App\Models\History\History;
use App\Models\History\HistoryType;
use App\Models\Payment\Payment;
use App\Services\OrderService;

/**
 * Class PaymentObserver
 * @package App\Observers\Payment
 */
class PaymentObserver
{
    /**
     * Handle the order "created" event.
     * @param  Payment $payment
     * @return void
     */
    public function created(Payment $payment)
    {
        History::saveEvent(HistoryType::TYPE_CREATE, $payment->order, $payment);
    }

    /**
     * Handle the order "updated" event.
     * @param  Payment $payment
     * @return void
     */
    public function updated(Payment $payment)
    {
        History::saveEvent(HistoryType::TYPE_UPDATE, $payment->order, $payment);
    }

    /**
     * Handle the order "saved" event.
     * @param  Payment $payment
     * @return void
     */
    public function saved(Payment $payment)
    {
        if ($payment->getOriginal('status') != $payment->status) {
            /** @var OrderService $orderService */
            $orderService = resolve(OrderService::class);
            $orderService->refreshPaymentStatus($payment->order->id);
        }
    }
    
    /**
     * Handle the order "deleting" event.
     * @param  Payment $payment
     * @throws \Exception
     */
    public function deleting(Payment $payment)
    {
        History::saveEvent(HistoryType::TYPE_DELETE, $payment->order, $payment);
    }
}
