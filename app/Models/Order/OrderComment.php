<?php

namespace App\Models\Order;

use App\Models\OmsModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 *
 * @OA\Schema(
 *     description="Комментарии к заказам",
 *     @OA\Property(
 *         property="order_id",
 *         type="integer",
 *         description="ID заказа"
 *     ),
 *     @OA\Property(
 *         property="text",
 *         type="string",
 *         description="Текст комментария"
 *     ),
 * )
 *
 * Class OrderComment
 * @package App\Models\Order
 *
 * @property int $order_id
 * @property string $text
 *
 * @property Order $order
 */
class OrderComment extends OmsModel
{
    /** @var string */
    protected $table = 'orders_comments';

    /**
     * Заполняемые поля модели
     */
    public const FILLABLE = [
        'text',
        'order_id',
    ];

    /** @var array */
    protected $fillable = self::FILLABLE;

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
