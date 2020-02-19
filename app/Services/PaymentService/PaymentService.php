<?php

namespace App\Services\PaymentService;

use App\Models\Payment\Payment;
use App\Models\Payment\PaymentStatus;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Класс-бизнес логики по работе с оплатами заказа
 * Class PaymentService
 * @package App\Services\PaymentService
 */
class PaymentService
{
    /** @var array|Payment[] - кэш объектов заказов с id в качестве ключа */
    public static $paymentsCached = [];

    /**
     * Получить объект оплаты по его id
     * @param  int  $paymentId
     * @return Payment|null
     */
    public function getPayment(int $paymentId): ?Payment
    {
        if (!isset(static::$paymentsCached[$paymentId])) {
            static::$paymentsCached[$paymentId] = Payment::find($paymentId);
        }

        return static::$paymentsCached[$paymentId];
    }

    /**
     * @param  Payment  $payment
     */
    public function addPayment2Cache(Payment $payment): void
    {
        static::$paymentsCached[$payment->id] = $payment;
    }

    /**
     * Начать оплату.
     * Задаёт время когда оплата станет просроченной, и создаёт оплату во внешней системе оплаты.
     * @param int $paymentId
     * @param string $returnUrl
     * @return string адрес страницы оплаты во внешней системе
     */
    public function start(int $paymentId, string $returnUrl): ?string
    {
        $payment = $this->getPayment($paymentId);
        if ($payment) {
            return null;
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
     * Установить статус оплаты "Просрочено"
     * @param  int  $paymentId
     * @return bool
     */
    public function timeout(int $paymentId): bool
    {
        $payment = $this->getPayment($paymentId);
        if ($payment) {
            return false;
        }

        $payment->status = PaymentStatus::TIMEOUT;

        return $payment->save();
    }

    /**
     * Получить список просроченных оплат.
     * @return Collection|Payment[]
     */
    public static function expiredPayments(): Collection
    {
        return Payment::query()->where('status', PaymentStatus::NOT_PAID)
            ->where('expires_at', '<', Carbon::now()->format('Y-m-d H:i:s'))
            ->get(['id', 'order_id']);
    }
}
