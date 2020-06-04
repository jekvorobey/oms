<?php

namespace App\Services;

use App\Models\Basket\Basket;
use App\Models\Basket\BasketItem;
use Exception;

/**
 * Класс-бизнес логики по работе с корзинами
 * Class BasketService
 * @package App\Services
 */
class BasketService
{
    /**
     * Получить объект корзины по его id
     * @param  int  $basketId
     * @return Basket|null
     */
    public function getBasket(int $basketId): ?Basket
    {
        return Basket::find($basketId);
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
        if (is_null($basket)) {
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
     * @param  int  $basketId
     * @param  int  $offerId
     * @param  int|null  $bundleId
     * @return BasketItem|null
     */
    public function itemByOffer(int $basketId, int $offerId, ?int $bundleId = null): ?BasketItem
    {
        $basket = $this->getBasket($basketId);
        if (is_null($basket)) {
            return null;
        }

        $item = $basket->items->first(function (BasketItem $item) use ($offerId, $bundleId) {
            return $item->offer_id == $offerId && $bundleId && $item->bundle_id == $bundleId;
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
     * @throws Exception
     */
    public function setItem(int $basketId, int $offerId, array $data): bool
    {
        $basket = $this->getBasket($basketId);
        if (is_null($basket)) {
            return false;
        }

        $item = $this->itemByOffer($basketId, $offerId, $data['bundle_id'] ?? null);
        if (!$item) {
            return false;
        }

        if ($item->id && isset($data['qty']) && !$data['qty']) {
            $ok = $item->delete();
        } else {
            if (isset($data['qty']) && $data['qty'] > 0) {
                $item->qty = $data['qty'];
            }
            if (array_key_exists('referrer_id', $data) && !$data['referrer_id']) {
                unset($data['referrer_id']);
            }
            $item->fill($data);
            $item->setDataByType($data);
            $ok = $item->save();
        }

        return $ok;
    }

    /**
     * Удалить корзину
     * @param  int  $basketId
     * @return bool
     * @throws Exception
     */
    public function deleteBasket(int $basketId): bool
    {
        $basket = $this->getBasket($basketId);
        if (is_null($basket)) {
            return false;
        }

        return !$basket->is_belongs_to_order ? $basket->delete() : false;
    }
}
