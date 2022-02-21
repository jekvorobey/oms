<?php

namespace App\Services;

use App\Models\Basket\Basket;
use App\Models\Basket\BasketItem;
use Illuminate\Support\Facades\Cache;

class GuestBasketService
{
    private const CACHE_PREFIX = 'guest_basket';

    public function getBasketFromGuest(string $basketId): Basket
    {

        return Basket::findOrFail($basketId);
    }

    public function findFreeUserBasketInGuest(int $type, string $customerId): Basket
    {
        $basket = Basket::query()
            ->select('id')
            ->where('customer_id', $customerId)
            ->where('type', $type)
            ->where('is_belongs_to_order', 0)
            ->first();
        if (is_null($basket)) {
            $basket = $this->createBasketInGuest($type, $customerId);
        }

        return $basket;
    }

    /**
     * Создать корзину в кэше
     */
    protected function createBasketInGuest(int $type, string $customerId): Basket
    {
        $basket = new Basket();
        $basket->customer_id = $customerId;
        $basket->type = $type;
        $basket->is_belongs_to_order = false;

        return $basket;
    }

    public function dropBasketInGuest(Basket $basket): bool
    {

        return $basket->delete();
    }

    /**
     * Создать/изменить/удалить товар временной корзины
     */
    public function setItem(Basket $basket, int $offerId, array $data): bool
    {
//        $item = $this->itemByOffer($basket, $offerId, $data['bundle_id'] ?? null, $data['bundle_item_id'] ?? null);
//        if ($item->id && isset($data['qty']) && !$data['qty']) {
//            $ok = $item->delete();
//        } else {
//            if (isset($data['qty']) && $data['qty'] > 0) {
//                $item->qty = $data['qty'];
//            }
//            if (array_key_exists('referrer_id', $data) && !$data['referrer_id']) {
//                unset($data['referrer_id']);
//            }
//
//            $item->fill($data);
//            $item->setDataByType($data);
//            $ok = $item->save();
//
//            if ($item->wasRecentlyCreated) {
//                $basket->items->push($item);
//            }
//        }

        return $ok;
    }

    /**
     * Получить объект товар корзины
     */
    protected function itemByOffer(
        Basket $basket,
        int $offerId,
        ?int $bundleId = null,
        ?int $bundleItemId = null
    ): BasketItem {
        $item = $basket->items->first(function (BasketItem $item) use ($offerId, $bundleId, $bundleItemId) {
            return $bundleId
                ? $item->offer_id == $offerId && $item->bundle_id == $bundleId && $item->bundle_item_id === $bundleItemId
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
}
