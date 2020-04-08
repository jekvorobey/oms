<?php

namespace App\Models\Order;

use App\Models\OmsModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Информация о скидках, примененные к заказу
 * Class OrderDiscount
 * @package App\Models\Order
 *
 * @property int        $order_id
 * @property int        $discount_id
 * @property string     $name
 * @property int        $type
 * @property int        $change
 * @property int|null   $merchant_id
 * @property bool       $promo_code_only
 * @property bool       $visible_in_catalog
 * @property array|null $items
 *
 * @property Order      $order
 */
class OrderDiscount extends OmsModel
{
    /**
     * Заполняемые поля модели
     */
    const FILLABLE = [
        'order_id',
        'discount_id',
        'name',
        'type',
        'change',
        'merchant_id',
        'promo_code_only',
        'visible_in_catalog',
        'items',
    ];

    /**
     * @var array
     */
    protected $fillable = self::FILLABLE;

    /** @var array */
    protected $casts = ['items' => 'array'];

    /**
     * @return BelongsTo
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
