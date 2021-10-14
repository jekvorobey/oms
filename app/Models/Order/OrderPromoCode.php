<?php

namespace App\Models\Order;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Greensight\CommonMsa\Models\AbstractModel;

/**
 * @OA\Schema(
 *     description="Промокоды применённые к заказу",
 *     @OA\Property(property="id", type="integer", description="ID"),
 *     @OA\Property(property="order_id", type="integer", description="ID заказа"),
 *     @OA\Property(property="promo_code_id", type="integer", description="ID промо кода"),
 *     @OA\Property(property="name", type="string", description="имя"),
 *     @OA\Property(property="code", type="number", description="код"),
 *     @OA\Property(property="type", type="integer", description="PromoCodeOutDto::TYPE_DISCOUNT, etc..."),
 *     @OA\Property(property="discount_id", type="integer", description=""),
 *     @OA\Property(property="gift_id", type="integer", description=""),
 *     @OA\Property(property="bonus_id", type="integer", description=""),
 *     @OA\Property(property="owner_id", type="integer", description="id реферального партнёра"),
 *
 * )
 *
 * Class OrderPromoCodes
 * @package App\Models\Order
 *
 * @property int $id
 * @property int $order_id
 * @property int $promo_code_id
 * @property string $name
 * @property string $code
 * @property int $type - PromoCodeOutDto::TYPE_DISCOUNT, etc...
 * @property int|null $discount_id
 * @property int|null $gift_id
 * @property int|null $bonus_id
 * @property int|null $owner_id - id реферального партнёра
 */

class OrderPromoCode extends AbstractModel
{
    /** @var string */
    protected $table = 'order_promo_codes';

    /**
     * Заполняемые поля модели
     */
    public const FILLABLE = [
        'order_id',
        'promo_code_id',
        'name',
        'code',
        'type',
        'status',
        'discount_id',
        'gift_id',
        'bonus_id',
        'owner_id',
    ];

    /**
     * Статус промокода
     */
    /** Активна */
    public const STATUS_ACTIVE = 4;

    /** Тестовый */
    public const STATUS_TEST = 8;

    /** @var array */
    protected $fillable = self::FILLABLE;
    /** @var bool */
    protected static $unguarded = true;

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
