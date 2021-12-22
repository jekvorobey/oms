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
     */
    public function getBasket(int $basketId): Basket
    {
        return Basket::findOrFail($basketId);
    }

    /**
     * Получить текущую корзину пользователя
     */
    public function findFreeUserBasket(int $type, int $customerId): Basket
    {
        $basket = Basket::query()
            ->select('id')
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
     */
    protected function itemByOffer(Basket $basket, int $offerId, ?int $bundleId = null): BasketItem
    {
        $item = $basket->items->first(function (BasketItem $item) use ($offerId, $bundleId) {
            return $bundleId
                ? $item->offer_id == $offerId && $item->bundle_id == $bundleId
                : $item->offer_id == $offerId && is_null($item->bundle_id);
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
     * @param array $data
     * @return bool|null
     * @throws Exception
     */
    public function setItem(Basket $basket, int $offerId, array $data): bool
    {
        $item = $this->itemByOffer($basket, $offerId, $data['bundle_id'] ?? null);

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
     * @throws Exception
     */
    public function deleteBasket(Basket $basket): bool
    {
        if ($basket->is_belongs_to_order) {
            return false;
        }

        return $basket->delete();
    }
}
