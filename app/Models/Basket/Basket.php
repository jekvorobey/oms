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
    public const TYPE_PRODUCT = 1;
    public const TYPE_MASTER = 2;
    
    /** @var bool */
    protected static $unguarded = true;
    
    /**
     * Получить текущую корзину пользователя.
     * @param int $type
     * @param int $customerId
     * @return Basket
     */
    public static function findFreeUserBasket(int $type, int $customerId): self
    {
        $basket = self::query()
            ->where('customer_id', $customerId)
            ->where('type', $type)
            ->where('is_belongs_to_order', 0)
            ->first();
        if (!$basket) {
            $basket = new self();
            $basket->customer_id = $customerId;
            $basket->type = $type;
            $basket->save();
        }
        
        return $basket;
    }
    
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
    
    /**
     * Получить объект товар корзины, даже если его нет в БД.
     * @param  int  $offerId
     * @return BasketItem
     */
    public function itemByOffer(int $offerId): BasketItem
    {
        $item = $this->items->first(function (BasketItem $item) use ($offerId) {
            return $item->offer_id == $offerId;
        });
        
        if (!$item) {
            $item = new BasketItem();
            $item->offer_id = $offerId;
            $item->basket_id = $this->id;
            $item->type = $this->type;
        }
        
        return $item;
    }
    
    /**
     * Создать/изменить/удалить товар корзины.
     * @param  int  $offerId
     * @param  array  $data
     * @return bool|null
     * @throws \Exception
     */
    public function setItem(int $offerId, array $data): bool
    {
        $item = $this->itemByOffer($offerId);
        
        if ($item->id && isset($data['qty']) && $data['qty'] === 0) {
            $ok = $item->delete();
        } else {
            if (isset($data['qty'])) {
                $item->qty = $data['qty'];
            }
            $item->setDataByType();
            $ok = $item->save();
        }
        
        return $ok;
    }
}
