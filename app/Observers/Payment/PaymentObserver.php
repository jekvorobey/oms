<?php

namespace App\Observers\Payment;

use App\Models\Basket\Basket;
use App\Models\Order\Order;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentStatus;
use App\Models\Payment\PaymentType;
use App\Services\OrderService;
use App\Services\PaymentService\PaymentService;

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
        if ($this->isNeedCreateIncomeReceipt($payment)) {
            $paymentService = new PaymentService();
            $paymentService->sendIncomePrepaymentReceipt($payment);
        }
    }

    public function isNeedCreateIncomeReceipt(Payment $payment): bool
    {
        switch ($payment->order->type) {
            case Basket::TYPE_PRODUCT:
            case Basket::TYPE_CERTIFICATE:
                return in_array($payment->status, [PaymentStatus::HOLD, PaymentStatus::PAID]);
            case Basket::TYPE_MASTER:
                return $payment->order->price > 0 && $payment->status == PaymentStatus::PAID;
            default:
                return false;
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
                $paymentSystem->createRefundAllReceipt($payment);
            }
        }
    }
}
