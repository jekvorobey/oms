<?php

namespace App\Models\Order;

use App\Models\OmsModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Информация о бонусах, полученных в заказе
 * Class OrderBonus
 * @package App\Models\Order
 *
 * @property int        $order_id
 * @property int        $bonus_id
 * @property string     $name
 * @property int        $type
 * @property int        $bonus
 * @property int        $valid_period (период действия бонуса в днях)
 * @property array|null $items
 */
class OrderBonus extends OmsModel
{
    /**
     * Заполняемые поля модели
     */
    const FILLABLE = [
        'order_id',
        'bonus_id',
        'name',
        'type',
        'bonus',
        'valid_period',
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
