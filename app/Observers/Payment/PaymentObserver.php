<?php

namespace App\Observers\Payment;

use App\Models\Order\Order;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentStatus;
use App\Models\Payment\PaymentType;
use App\Services\OrderService;

/**
 * Class PaymentObserver
 * @package App\Observers\Payment
 */
class PaymentObserver
{
    public function saving(Payment $payment): void
    {
        if ($payment->isDirty('status') && $payment->status === PaymentStatus::PAID) {
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

        if ($payment->wasChanged('payment_type') && $payment->payment_type) {
            $order = $payment->order;
            $order->can_partially_cancelled = !in_array($payment->payment_type, PaymentType::typesWithoutPartiallyCancel(), true);
            $order->save();
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
        $order = $payment->order;
        $checkingStatuses = [PaymentStatus::PAID];
        if (!$order->isPublicEventOrder()) {
            $checkingStatuses[] = PaymentStatus::HOLD;
        }

        if (
            in_array($payment->status, $checkingStatuses, true)
            && !$payment->is_receipt_sent
            && $this->isNeedCreateIncomeReceipt($payment)
        ) {
            $paymentSystem = $payment->paymentSystem();
            if ($paymentSystem) {
                $paymentSystem->createIncomeReceipt($payment->order, $payment);
                $payment->is_receipt_sent = true;
                $payment->save();
            }
        }
    }

    public function isNeedCreateIncomeReceipt(Payment $payment): bool
    {
        if ($payment->order->isProductOrder() || $payment->order->isCertificateOrder()) {
            return true;
        }

        if ($payment->order->isPublicEventOrder()) {
            return $payment->order->price > 0;
        }
    }

    public function createRefundReceipt(Payment $payment): void
    {
        if (
            $payment->status === PaymentStatus::TIMEOUT
            && in_array($payment->getOriginal('status'), [PaymentStatus::HOLD, PaymentStatus::PAID])
        ) {
            $paymentSystem = $payment->paymentSystem();

            if ($paymentSystem) {
                $paymentSystem->createRefundAllReceipt($payment->order, $payment);
            }
        }
    }
}
