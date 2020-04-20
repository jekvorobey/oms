<?php

namespace App\Models\Order;

use App\Models\OmsModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


/**
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
class OrderPromoCode extends OmsModel
{
    /** @var string */
    protected $table = 'order_promo_codes';

    /**
     * Заполняемые поля модели
     */
    const FILLABLE = [
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
    const STATUS_ACTIVE = 4;
    /** Тестовый */
    const STATUS_TEST = 8;

    /**
     * @var array
     */
    protected $fillable = self::FILLABLE;

    /**
     * @return BelongsTo
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
