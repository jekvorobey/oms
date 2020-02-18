<?php

namespace App\Models\Basket;

use App\Models\OmsModel;
use App\Models\Order\Order;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

/**
 * Класс-модель для сущности "Корзина"
 * Class Basket
 * @package App\Models
 *
 * @property int $customer_id - id покупателя
 * @property bool $is_belongs_to_order - корзина принадлежит заказу? (поле необходимо для удаления старых корзин без заказов)
 * @property int $type - тип корзины (Basket::TYPE_PRODUCT|Basket::TYPE_MASTER)
 *
 * @property-read Order|null $order - заказ
 * @property-read Collection|BasketItem[] $items - элементы (товары)
 */
class Basket extends OmsModel
{
    /** @var int - корзина с товарами */
    public const TYPE_PRODUCT = 1;
    /** @var int - корзина с мастер-классами */
    public const TYPE_MASTER = 2;
    
    /** @var bool */
    protected static $unguarded = true;
    
    /**
     * @return HasOne
     */
    public function order(): HasOne
    {
        return $this->hasOne(Order::class);
    }
    
    /**
     * @return HasMany
     */
    public function items(): HasMany
    {
        return $this->hasMany(BasketItem::class);
    }
}
