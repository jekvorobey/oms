<?php

namespace App\Services\BasketService;

use App\Models\Basket\Basket;
use App\Models\Basket\BasketItem;

abstract class BasketService
{
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
