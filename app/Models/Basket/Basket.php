<?php

namespace App\Models\Basket;

use App\Models\OmsModel;
use App\Models\Order\Order;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Класс-модель для сущности "Корзина"
 * Class Basket
 * @package App\Models
 *
 * @property int $customer_id - id покупателя
 *
 * @property bool $is_belongs_to_order - корзина принадлежит заказу? (поле необходимо для удаления старых корзин без заказов)
 *
 * @property-read Order|null $order - заказ
 * @property-read Collection|BasketItem[] $items - элементы (товары)
 */
class Basket extends OmsModel
{
    protected static $unguarded = true;
    
    /**
     * Получить текущую корзину пользователя.
     *
     * @param int $customerId
     * @return Basket
     */
    public static function findFreeUserBasket(int $customerId): self
    {
        $basket = self::query()->where('customer_id', $customerId)
            ->whereNull('order_id')
            ->first();
        if (!$basket) {
            $basket = new self();
            $basket->customer_id = $customerId;
            $basket->save();
        }
        return $basket;
    }
    
    /**
     * @return BelongsTo
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'id', 'basket_id');
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
     *
     * @param int $offerId
     * @return BasketItem
     */
    public function itemByOffer(int $offerId)
    {
        $item = $this->items->first(function (BasketItem $item) use ($offerId) {
            return $item->offer_id == $offerId;
        });
        if (!$item) {
            $item = new BasketItem();
            $item->offer_id = $offerId;
            $item->basket_id = $this->id;
        }
        return $item;
    }
    
    /**
     * Создать/изменить/удалить товар корзины.
     *
     * @param int $offerId
     * @param array $data
     * @return bool|null
     * @throws \Exception
     */
    public function setItem(int $offerId, array $data)
    {
        $item = $this->itemByOffer($offerId);
        if ($item->id && isset($data['qty']) && $data['qty'] === 0) {
            $ok = $item->delete();
        } else {
            if (isset($data['qty'])) {
                $item->qty = $data['qty'];
            }
            if (isset($data['name'])) {
                $item->name = $data['name'];
            }
            $ok = $item->save();
        }
        return $ok;
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
