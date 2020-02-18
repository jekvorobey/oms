<?php

namespace App\Services;

use App\Models\Basket\Basket;
use App\Models\Basket\BasketItem;

/**
 * Класс-бизнес логики по работе с корзинами
 * Class BasketService
 * @package App\Services
 */
class BasketService
{
    /** @var array|Basket[] - кэш объектов заказов с id в качестве ключа */
    public static $basketsCached = [];

    /**
     * Получить объект корзины по его id
     * @param  int  $basketId
     * @return Basket|null
     */
    public function getBasket(int $basketId): ?Basket
    {
        if (!isset(static::$basketsCached[$basketId])) {
            static::$basketsCached[$basketId] = Basket::find($basketId);
        }

        return static::$basketsCached[$basketId];
    }

    /**
     * Получить текущую корзину пользователя
     * @param int $type
     * @param int $customerId
     * @return Basket
     */
    public function findFreeUserBasket(int $type, int $customerId): Basket
    {
        $basket = Basket::query()
            ->where('customer_id', $customerId)
            ->where('type', $type)
            ->where('is_belongs_to_order', 0)
            ->first();
        if (!$basket) {
            $basket = $this->createBasket($type, $customerId);
        }

        return $basket;
    }

    /**
     * Создать корзину
     * @param  int  $type
     * @param  int  $customerId
     * @return Basket
     */
    protected function createBasket(int $type, int $customerId): Basket
    {
        $basket = new Basket();
        $basket->customer_id = $customerId;
        $basket->type = $type;
        $basket->is_belongs_to_order = false;
        $basket->save();

        return $basket;
    }

    /**
     * Получить объект товар корзины, даже если его нет в БД
     * @param  int  $offerId
     * @return BasketItem|null
     */
    public function itemByOffer(int $basketId, int $offerId): ?BasketItem
    {
        $basket = $this->getBasket($basketId);
        if ($basket) {
            return null;
        }

        $item = $basket->items->first(function (BasketItem $item) use ($offerId) {
            return $item->offer_id == $offerId;
        });

        if (!$item) {
            $item = new BasketItem();
            $item->offer_id = $offerId;
            $item->basket_id = $basket->id;
            $item->type = $basket->type;
        }

        return $item;
    }

    /**
     * Создать/изменить/удалить товар корзины
     * @param int $basketId
     * @param  int  $offerId
     * @param  array  $data
     * @return bool|null
     * @throws \Exception
     */
    public function setItem(int $basketId, int $offerId, array $data): bool
    {
        $basket = $this->getBasket($basketId);
        if (is_null($basket)) {
            return false;
        }

        $item = $this->itemByOffer($basketId, $offerId);
        if (!$item) {
            return false;
        }

        if ($item->id && isset($data['qty']) && !$data['qty']) {
            $ok = $item->delete();
        } else {
            if (isset($data['qty']) && $data['qty'] > 0) {
                $item->qty = $data['qty'];
            }
            $item->setDataByType();
            $item->fill($data);
            $ok = $item->save();
        }

        return $ok;
    }
}
