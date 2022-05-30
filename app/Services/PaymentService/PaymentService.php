<?php

namespace App\Services\PaymentService;

use App\Models\Basket\BasketItem;
use App\Models\Order\Order;
use App\Models\Order\OrderStatus;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentStatus;
use App\Services\PaymentService\PaymentSystems\Exceptions\Payment as PaymentException;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Pim\Services\CertificateService\CertificateService;

/**
 * Класс-бизнес логики по работе с оплатами заказа
 * Class PaymentService
 * @package App\Services\PaymentService
 */
class PaymentService
{
    /**
     * Получить объект оплаты по его id
     */
    public function getPayment(int $paymentId): ?Payment
    {
        return Payment::find($paymentId);
    }

    /**
     * Начать оплату.
     * Задаёт время когда оплата станет просроченной, и создаёт оплату во внешней системе оплаты.
     * @return string адрес страницы оплаты во внешней системе
     */
    public function start(int $paymentId, string $returnUrl): ?string
    {
        $payment = $this->getPayment($paymentId);
        if (is_null($payment)) {
            return null;
        }

        if ($payment->sum == 0) {
            if ($payment->order->spent_certificate > 0) {
                $payment->external_payment_id = $this->getCertificatePaymentId($payment->order);
                $payment->save();
            }

            if (!$this->pay($payment)) {
                throw new \Exception('Ошибка при автоматической оплате');
            }

            return $returnUrl;
        }

        $paymentSystem = $payment->paymentSystem();
        $hours = $paymentSystem->duration();
        if ($hours) {
            $payment->expires_at = Carbon::now()->addHours($hours);
        }
        $payment->save();
        $paymentSystem->createExternalPayment($payment, $returnUrl);

        return $payment->payment_link;
    }

    /**
     * Получение id платежа юкассы покупки подарочного сертификата
     */
    private function getCertificatePaymentId(Order $order): ?string
    {
        $certificate = head($order->certificates);
        $certificateService = resolve(CertificateService::class);

        if (!isset($certificate['id'])) {
            throw new PaymentException('Certificate id in order not found');
        }

        $certificateQuery = $certificateService->certificateQuery()->id($certificate['id']);
        $certificateInfo = $certificateService->certificates($certificateQuery);

        if ($certificateInfo->isEmpty()) {
            throw new PaymentException('Certificates not found');
        }

        $certificateRequests = $certificateInfo->pluck('request_id')->toArray();

        /** @var BasketItem $certificateBasketItem */
        $certificateBasketItem = BasketItem::query()
            ->whereIn('product->request_id', $certificateRequests)
            ->with('basket.order.payments')
            ->firstOrFail();

        /** @var Payment $certificatePayment */
        $certificatePayment = $certificateBasketItem->basket->order->payments->first();

        return $certificatePayment->external_payment_id ?? null;
    }

    /**
     * Установить статус оплаты "Оплачена"
     */
    public function pay(Payment $payment): bool
    {
        $payment->status = PaymentStatus::PAID;

        return $payment->save();
    }

    /**
     * Установить статус оплаты "Просрочено"
     */
    public function timeout(Payment $payment): bool
    {
        $payment->status = PaymentStatus::TIMEOUT;

        return $payment->save();
    }

    /**
     * Установить статус оплаты "Ожидает оплаты"
     */
    public function waiting(Payment $payment): bool
    {
        $payment->status = PaymentStatus::WAITING;

        return $payment->save();
    }

    /**
     * Получить список просроченных оплат.
     * @return Collection|Payment[]
     */
    public static function expiredPayments(): Collection
    {
        return Payment::query()->whereIn('status', [PaymentStatus::NOT_PAID, PaymentStatus::WAITING])
            ->where('expires_at', '<', Carbon::now()->format('Y-m-d H:i:s'))
            ->get(['id', 'order_id']);
    }

    public function refund(Order $order, int $sum): void
    {
        /** @var Payment $payment */
        $payment = $order->payments->last();
        $refundSum = min($payment->sum, $payment->refund_sum + $sum);

        $payment->refund_sum = $refundSum;
        $payment->save();
    }

    public function capture(Payment $payment): void
    {
        $paymentSystem = $payment->paymentSystem();
        if (!$paymentSystem) {
            return;
        }

        $paymentSystem->commitHoldedPayment($payment, $payment->sum - (float) $payment->refund_sum);

        if ($payment->refund_sum > 0) {
            $paymentSystem->createRefundAllReceipt($payment);

            if ($payment->order->status == OrderStatus::DONE) {
                $this->sendIncomeFullPaymentReceipt($payment);
            } else {
                $this->sendIncomePrepaymentReceipt($payment, true);
            }
        }
    }

    public function sendIncomePrepaymentReceipt(Payment $payment, bool $force = false): void
    {
        if (!$force && $payment->is_prepayment_receipt_sent) {
            return;
        }

        $paymentSystem = $payment->paymentSystem();
        if (!$paymentSystem) {
            return;
        }

        $paymentSystem->createIncomeReceipt($payment, false);

        $payment->is_prepayment_receipt_sent = true;
        $payment->save();
    }

    public function sendIncomeFullPaymentReceipt(Payment $payment): void
    {
        if ($payment->is_fullpayment_receipt_sent) {
            return;
        }

        $paymentSystem = $payment->paymentSystem();
        if (!$paymentSystem) {
            return;
        }

        $paymentSystem->createIncomeReceipt($payment, true);

        $payment->is_fullpayment_receipt_sent = true;
        $payment->save();
    }

    public function updatePaymentInfo(Payment $payment): void
    {
        $paymentSystem = $payment->paymentSystem();
        if (!$paymentSystem) {
            return;
        }

        $paymentInfo = null;
        if ($payment->external_payment_id) {
            $paymentInfo = $paymentSystem->paymentInfo($payment);
        }
        if (!$paymentInfo) {
            $this->timeout($payment);
            return;
        }

        $paymentSystem->updatePaymentStatus($payment, $paymentInfo);
    }
}
