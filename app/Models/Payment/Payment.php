<?php

namespace App\Models\Payment;

use App\Core\Payment\LocalPaymentSystem;
use App\Core\Payment\PaymentSystemInterface;
use App\Core\Payment\YandexPaymentSystem;
use App\Models\OmsModel;
use App\Models\Order\Order;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * Class Payment
 * @package App\Models
 *
 * @property int $order_id
 * @property float $sum
 * @property Carbon $payed_at
 * @property Carbon $expires_at
 * @property int $status
 * @property int $payment_method
 * @property int $payment_system
 * @property array $data
 *
 * @property-read Order $order
 */
class Payment extends OmsModel
{
    /** @var bool */
    public $timestamps = false;
    /** @var bool */
    protected static $unguarded = true;

    /** @var array */
    protected $dates = ['created_at', 'payed_at', 'expires_at'];
    /** @var array */
    protected $casts = ['data' => 'array'];

    /**
     * Получить оплату по id
     *
     * @param int $id
     * @return Payment|null
     */
    public static function findById(int $id): ?Payment
    {
        /** @var Payment $payment */
        $payment = Payment::query()->where('id', $id)->first();
        return $payment;
    }

    /**
     * Получить список просроченных оплат.
     *
     * @return Collection|Payment[]
     */
    public static function expiredPayments(): Collection
    {
        return Payment::query()->where('status', PaymentStatus::NOT_PAID)
            ->where('expires_at', '<', Carbon::now()->format('Y-m-d H:i:s'))
            ->get(['id', 'order_id']);
    }

    /**
     * Payment constructor.
     * @param  array  $attributes
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        if (!$this->created_at) {
            $this->created_at = Carbon::now();
        }
        if (!$this->data) {
            $this->data = [];
        }
    }

    /**
     * Начать оплату.
     * Задаёт время когда оплата станет просроченной, и создаёт оплату во внешней системе оплаты.
     *
     * @param Payment $payment
     * @param string $returnUrl
     * @return string адрес страницы оплаты во внешней системе
     */
    public function start(string $returnUrl)
    {
        $paymentSystem = $this->paymentSystem();
        $hours = $paymentSystem->duration();
        if ($hours) {
            $this->expires_at = Carbon::now()->addHours($hours);
        }
        $this->save();
        $paymentSystem->createExternalPayment($this, $returnUrl);

        return $paymentSystem->paymentLink($this);
    }

    public function timeout(): void
    {
        $this->status = PaymentStatus::TIMEOUT;
        $this->save();
    }

    /**
     * @return PaymentSystemInterface|null
     */
    public function paymentSystem(): ?PaymentSystemInterface
    {
        switch ($this->payment_system) {
            case PaymentSystem::YANDEX: return new YandexPaymentSystem();
            case PaymentSystem::TEST: return new LocalPaymentSystem();
        }
        return null;
    }

    /**
     * @return BelongsTo
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
