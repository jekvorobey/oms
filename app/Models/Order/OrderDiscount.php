<?php

namespace App\Models\Order;

use App\Models\OmsModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\JoinClause;

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
     * Учитывать только те скидки на заказы, в которых использовалась скидка $discountId,
     * данная скидка должна быть либо активирована без промокода,
     * либо активирована промокодом, но со статусом ACTIVE
     *
     * @param Builder $query
     * @param int     $discountId
     */
    public function scopeForDiscountReport(Builder $query, int $discountId)
    {
        $d = with(new OrderDiscount)->getTable();
        $p = with(new OrderPromoCode)->getTable();

        $query
            ->where("{$d}.discount_id", $discountId)
            ->leftJoin($p, function(JoinClause $join) use ($p, $d) {
                $join->on("{$d}.discount_id", '=', "{$p}.discount_id");
                $join->on("{$d}.order_id", '=', "{$p}.order_id");
            })
            ->where(function (Builder $query) use ($p) {
                $query
                    ->where("{$p}.status", OrderPromoCode::STATUS_ACTIVE)
                    ->orWhereNull("{$p}.id");
            });
    }

    /**
     * @return BelongsTo
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
