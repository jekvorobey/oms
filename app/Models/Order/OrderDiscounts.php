<?php

namespace App\Models\Order;

use App\Models\OmsModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Информация о скидках, примененные к заказу
 * Class OrderDiscounts
 * @package App\Models\Order
 *
 * @property int $order_id
 * @property OrderDiscount[]|null $discounts
 *
 * @property Order $order
 */
class OrderDiscounts extends OmsModel
{
    /** @var string */
    protected $table = 'order_discounts';

    /**
     * Заполняемые поля модели
     */
    const FILLABLE = [
        'order_id',
        'discounts',
    ];

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

    /**
     * @param  array  $discounts
     * @return void
     */
    public function setDiscountsAttribute(array $discounts)
    {
        $discounts = array_map(function (OrderDiscount $orderDiscount) {
            return $orderDiscount->toArray();
        }, $discounts);

        $this->attributes['discounts'] = json_encode($discounts);
    }

    /**
     * @return array
     */
    public function getDiscountsAttribute()
    {
        return array_map(function ($discount) {
            return new OrderDiscount($discount);
        }, json_decode($this->attributes['discounts'], true));
    }
}
