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
    public function saving(Payment $payment): void
    {
        if ($payment->wasChanged('status') && $payment->status === PaymentStatus::PAID) {
            $payment->payed_at = now();
        }
    }

    public function saved(Payment $payment): void
    {
        if ($payment->wasChanged('status')) {
            $this->updateOrderPaymentStatus($payment->order);

            $this->createIncomeReceipt($payment);
            $this->createRefundReceipt($payment);
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

    public function createRefundReceipt(Payment $payment): void
    {
        if ($payment->wasChanged('status') && $payment->status === PaymentStatus::TIMEOUT) {
            $paymentSystem = $payment->paymentSystem();

            if ($paymentSystem) {
                $paymentSystem->createRefundAllReceipt($payment->order, $payment);
            }
        }
    }
}
