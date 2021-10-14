<?php

namespace App\Observers\Payment;

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
     * Handle the order "saved" event.
     * @return void
     */
    public function saved(Payment $payment)
    {
        if ($payment->wasChanged('status')) {
            $this->updateOrderPaymentStatus($payment->order);
            $this->createIncomeReceipt($payment);
        }
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
