<?php

namespace App\Models;

use Greensight\CommonMsa\Models\AbstractModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use App\Models\BasketItem;

/**
 * Класс-модель для сущности "Корзина"
 * Class Basket
 * @package App\Models
 *
 * @property int $customer_id - id покупателя
 * @property int $order_id - id заказа
 *
 * @property-read Order|null $order - заказ
 * @property-read Collection|BasketItem[] $items - элементы (товары)
 */
class Basket extends AbstractModel
{
    /**
     * Заполняемые поля модели
     */
    const FILLABLE = ['customer_id', 'order_id'];

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
     * @return HasMany
     */
    public function items(): HasMany
    {
        return $this->hasMany(BasketItem::class);
    }

    protected static function boot()
    {
        parent::boot();
        self::deleting(function (Basket $basket) {
            foreach ($basket->items as $item) {
                $item->delete();
            }
        });
    }
}
