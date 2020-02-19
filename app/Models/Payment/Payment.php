<?php

namespace App\Models\Payment;

use App\Models\OmsModel;
use App\Models\Order\Order;
use App\Services\PaymentService\PaymentSystems\LocalPaymentSystem;
use App\Services\PaymentService\PaymentSystems\PaymentSystemInterface;
use App\Services\PaymentService\PaymentSystems\YandexPaymentSystem;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
