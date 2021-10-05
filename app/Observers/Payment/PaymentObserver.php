<?php

namespace App\Observers\Payment;

use App\Models\History\History;
use App\Models\History\HistoryType;
use App\Models\Order\Order;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentStatus;
use App\Services\OrderService;

/**
 * Class PaymentObserver
 * @package App\Observers\Payment
 */
class PaymentObserver
{
    /**
     * Handle the order "created" event.
     * @return void
     */
    public function created(Payment $payment)
    {
        History::saveEvent(HistoryType::TYPE_CREATE, $payment->order, $payment);
        logger()->info('Payment created', ['payment' => $payment]);
    }

    /**
     * Handle the order "updated" event.
     * @return void
     */
    public function updated(Payment $payment)
    {
        History::saveEvent(HistoryType::TYPE_UPDATE, $payment->order, $payment);
        logger()->info('Payment updated', ['payment' => $payment]);
    }

    /**
     * Handle the order "saved" event.
     * @return void
     */
    public function saved(Payment $payment)
    {
        logger()->info('Payment saved', ['payment' => $payment]);
        if ($payment->wasChanged('status')) {
            $this->updateOrderPaymentStatus($payment->order);
            $this->createIncomeReceipt($payment);
        }
    }

    /**
     * Handle the order "deleting" event.
     * @throws \Exception
     */
    public function deleting(Payment $payment)
    {
        History::saveEvent(HistoryType::TYPE_DELETE, $payment->order, $payment);
    }

    public function updateOrderPaymentStatus(Order $order): void
    {
        /** @var OrderService $orderService */
        $orderService = resolve(OrderService::class);
        $orderService->refreshPaymentStatus($order);
    }

    public function createIncomeReceipt(Payment $payment): void
    {
        if (
            in_array($payment->status, [PaymentStatus::HOLD, PaymentStatus::PAID], true)
            && !$payment->is_receipt_sent
        ) {
            $paymentSystem = $payment->paymentSystem();
            if ($paymentSystem) {
                $paymentSystem->createIncomeReceipt($payment->order, $payment);
                $payment->is_receipt_sent = true;
                $payment->save();
            }
        }
    }
}
