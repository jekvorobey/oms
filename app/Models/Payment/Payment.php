<?php

namespace App\Models\Payment;

use App\Models\Order\Order;
use App\Models\WithHistory;
use App\Services\PaymentService\PaymentSystems\LocalPaymentSystem;
use App\Services\PaymentService\PaymentSystems\PaymentSystemInterface;
use App\Services\PaymentService\PaymentSystems\Yandex\YandexPaymentSystem;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Greensight\CommonMsa\Models\AbstractModel;

/**
 * @OA\Schema(
 *     description="Оплата",
 *     @OA\Property(
 *         property="order_id",
 *         type="integer",
 *         description="id заказа"
 *     ),
 *     @OA\Property(
 *         property="sum",
 *         type="number",
 *         description="сумма"
 *     ),
 *     @OA\Property(
 *         property="payed_at",
 *         type="string",
 *         description="дата оплаты"
 *     ),
 *     @OA\Property(
 *         property="expires_at",
 *         type="string",
 *         description=""
 *     ),
 *     @OA\Property(
 *         property="yandex_expires_at",
 *         type="string",
 *         description="дата и время до которого в яндексе отменить или подтвердить платеж"
 *     ),
 *     @OA\Property(
 *         property="status",
 *         type="integer",
 *         description="статус"
 *     ),
 *     @OA\Property(
 *         property="payment_method",
 *         type="integer",
 *         description="метод оплаты"
 *     ),
 *     @OA\Property(
 *         property="payment_system",
 *         type="integer",
 *         description="платежная система"
 *     ),
 *     @OA\Property(
 *         property="data",
 *         type="string",
 *         description="данные"
 *     ),
 * )
 *
 * Class Payment
 * @package App\Models
 *
 * @property int $order_id
 * @property float $sum
 * @property float $refund_sum
 * @property Carbon $payed_at
 * @property Carbon $expires_at
 * @property Carbon $yandex_expires_at - Дата и время до которого в яндексе отменить или подтвердить платеж
 * @property int $status
 * @property string $cancel_reason
 * @property int $payment_method
 * @property int $payment_system
 * @property string $payment_type
 * @property bool $is_prepayment_receipt_sent
 * @property bool $is_fullpayment_receipt_sent
 * @property bool $is_credit_receipt_sent
 * @property bool $is_credit_payment_receipt_sent
 * @property array $data
 *
 * @property-read Order $order
 * @property-read PaymentMethod $paymentMethod
 *
 * @property string|null $external_payment_id
 * @property string|null $payment_link
 *
 * @method static|Builder byExternalPaymentId(string|null $externalPaymentId)
 */
class Payment extends AbstractModel
{
    use WithHistory;

    /** @var bool */
    public $timestamps = false;
    /** @var bool */
    protected static $unguarded = true;

    /** @var array */
    protected $dates = ['created_at', 'payed_at', 'expires_at', 'yandex_expires_at'];
    /** @var array */
    protected $casts = [
        'data' => 'array',
        'status' => 'int',
    ];

    /**
     * Payment constructor.
     * @param array $attributes
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

    public function paymentSystem(): ?PaymentSystemInterface
    {
        switch ($this->payment_system) {
            case PaymentSystem::YANDEX:
                return new YandexPaymentSystem();
            case PaymentSystem::TEST:
                return new LocalPaymentSystem();
        }

        return null;
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    protected function historyMainModel(): ?Order
    {
        return $this->order;
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method');
    }

    public function getExternalPaymentIdAttribute(): ?string
    {
        return $this->data['externalPaymentId'] ?? null;
    }

    public function setExternalPaymentIdAttribute($value): void
    {
        $this->data = array_merge($this->data, ['externalPaymentId' => $value]);
    }

    public function scopeByExternalPaymentId(Builder $query, ?string $externalPaymentId): void
    {
        $query->where('data->externalPaymentId', $externalPaymentId);
    }

    public function getPaymentLinkAttribute(): ?string
    {
        return $this->data['paymentLink'] ?? null;
    }

    public function setPaymentLinkAttribute($value): void
    {
        $this->data = array_merge($this->data, ['paymentLink' => $value]);
    }
}
