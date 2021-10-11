<?php

namespace App\Services\PaymentService;

use App\Models\Basket\BasketItem;
use App\Models\Order\Order;
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
            $payment->data = [
                'externalPaymentId' => $this->getCertificatePaymentId($payment->order),
            ];
            $payment->save();

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

        return $paymentSystem->paymentLink($payment);
    }

    /**
     * Получение id платежа юкассы покупки подарочного сертификата
     */
    private function getCertificatePaymentId(Order $order): ?string
    {
        $certificate = current($order->certificates);
        $certificateService = resolve(CertificateService::class);

        if ($certificate['id']) {
            $certificateQuery = $certificateService->certificateQuery();
            $certificateQuery->id($certificate['id']);
            $certificateInfo = $certificateService->certificates($certificateQuery);

            if ($certificateInfo) {
                $certificateRequests = $certificateInfo->pluck('request_id')->toArray();

                /** @var BasketItem $certificateBasketItem */
                $certificateBasketItem = BasketItem::query()
                    ->whereIn('product->request_id', $certificateRequests)
                    ->with('basket.order.payments')
                    ->firstOrFail();

                $certificatePayment = $certificateBasketItem->basket->order->payments->first();
                $result = $certificatePayment->data['externalPaymentId'];
            } else {
                throw new PaymentException('Certificates not found');
            }
        } else {
            throw new PaymentException('Certificate id in order not found');
        }

        return $result;
    }

    /**
     * Установить статус оплаты "Оплачена"
     */
    public function pay(Payment $payment): bool
    {
        $payment->status = PaymentStatus::PAID;
        $payment->payed_at = Carbon::now();

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
        $refundSum = $payment->refund_sum + $sum;
        if ($payment->sum >= $refundSum && $refundSum > 0) {
            $payment->refund_sum = $refundSum;
            $payment->save();
        }
    }
}
