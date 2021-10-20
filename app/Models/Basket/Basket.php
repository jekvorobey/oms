<?php

namespace App\Models\Basket;

use App\Models\Order\Order;
use Greensight\CommonMsa\Models\AbstractModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

/**
 * @OA\Schema(
 *     description="Корзина",
 *     @OA\Property(
 *         property="customer_id",
 *         type="integer",
 *         description="id покупателя"
 *     ),
 *     @OA\Property(
 *         property="is_belongs_to_order",
 *         type="boolean",
 *         description="корзина принадлежит заказу? (поле необходимо для удаления старых корзин без заказов)"
 *     ),
 *     @OA\Property(
 *         property="type",
 *         type="integer",
 *         description="тип корзины (Basket::TYPE_PRODUCT|Basket::TYPE_MASTER)"
 *     ),
 * )
 *
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
class Basket extends AbstractModel
{
    /** корзина с товарами */
    public const TYPE_PRODUCT = 1;

    /** корзина с мастер-классами */
    public const TYPE_MASTER = 2;

    /** корзина с подарочными сертификатами */
    public const TYPE_CERTIFICATE = 3;

    /** @var bool */
    protected static $unguarded = true;

    public function order(): HasOne
    {
        return $this->hasOne(Order::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BasketItem::class);
    }

    /**
     * Корзина является корзиной с товарами?
     */
    public function isProductBasket(): bool
    {
        return $this->type == Basket::TYPE_PRODUCT;
    }

    /**
     * Корзина является корзиной с мастер-классами?
     */
    public function isPublicEventBasket(): bool
    {
        return $this->type == Basket::TYPE_MASTER;
    }
}
