<?php

namespace App\Models\Payment;

use App\Models\Order\Order;
use App\Models\WithHistory;
use Carbon\Carbon;
use Greensight\CommonMsa\Models\AbstractModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     description="Кассовые чеки",
 *     @OA\Property(
 *         property="guid",
 *         type="string",
 *         description="уникальный guid записи"
 *     ),
 *     @OA\Property(
 *         property="payment_id",
 *         type="integer",
 *         description="id оплаты"
 *     ),
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
 *         property="receipt_type",
 *         type="integer",
 *         description="тип чека"
 *     ),
 *     @OA\Property(
 *         property="status",
 *         type="integer",
 *         description="статус"
 *     ),
 *     @OA\Property(
 *         property="request",
 *         type="string",
 *         description="Отправленные данные"
 *     ),
 *     @OA\Property(
 *         property="response",
 *         type="string",
 *         description="Полученные данные"
 *     ),
 * )
 *
 * Class PaymentReceipt
 * @package App\Models
 *
 * @property string $guid - уникальный guid записи
 * @property int $payment_id
 * @property int $order_id
 * @property float $sum
 * @property Carbon $payed_at
 * @property int $status
 * @property int $receipt_type
 * @property array $request
 * @property array $response
 *
 * @property-read Order $order
 * @property-read Payment $payment
 */
class PaymentReceipt extends AbstractModel
{
    use WithHistory;

    public const TYPE_INCOME = 1;
    public const TYPE_PREPAYMENT = 2;
    public const TYPE_ON_CREDIT = 3;
    public const TYPE_CREDIT_PAYMENT = 4;
    public const TYPE_REFUND_ALL = -10;
    public const TYPE_REFUND_CANCEL = -1;

    /** @var bool */
    public $timestamps = false;

    /** @var bool */
    protected static $unguarded = true;

    /** @var array */
    protected $dates = ['created_at', 'payed_at'];

    /** @var array */
    protected $casts = [
        'request' => 'array',
        'response' => 'array',
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
        if (!$this->request) {
            $this->request = [];
        }
        if (!$this->response) {
            $this->response = [];
        }
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    protected function historyMainModel(): ?Payment
    {
        return $this->payment;
    }

}
