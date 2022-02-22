<?php

namespace App\Services;

use App\Models\Basket\Basket;
use App\Models\Basket\BasketItem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GuestBasketService
{
    private const CACHE_LIFETIME = 7 * 24 * 60;

    public function getGuestBasket(int $basketId): Basket
    {
        $basket = Cache::get($basketId);

        if (!$basket) {
            throw new \RuntimeException('Guest basket not found');
        }

        return $basket;
    }

    public function findFreeUserGuestBasket(int $type, string $customerId): Basket
    {
        $basketMappings = Cache::get($customerId);
        $key = '';
        switch ($type) {
            case Basket::TYPE_PRODUCT:
                $key = 'product';
                break;
            case Basket::TYPE_CERTIFICATE:
                $key = 'certificate';
                break;
            case Basket::TYPE_MASTER:
                $key = 'master';
                break;
        }

        if ($key) {
            if (isset($basketMappings[$key])) {
                $basketUuid = $basketMappings[$key];
                $basket = Cache::get($basketUuid);
            } else {
                $basket = $this->createGuestBasket($type, $customerId);
            }
        }

        Log::debug(json_encode([
            'basket' => $basket
        ]));

        return $basket;
    }

    /**
     * Создать корзину в кэше
     */
    protected function createGuestBasket(int $type, string $customerId): Basket
    {
        $basket = new Basket();
        $basket->id = random_int(
            round(10 ** 7),
            round(99 ** 7)
        );
        $basket->customer_id = $customerId;
        $basket->type = $type;
        $basket->is_belongs_to_order = false;

        Cache::put($basket->id, $basket,self::CACHE_LIFETIME);
        $basketMapping = Cache::get($basket->customer_id);

        $key = '';
        switch ($type) {
            case Basket::TYPE_PRODUCT:
                $key = 'product';
                break;
            case Basket::TYPE_CERTIFICATE:
                $key = 'certificate';
                break;
            case Basket::TYPE_MASTER:
                $key = 'master';
                break;
        }

        if ($key) {
            Cache::forget($basket->customer_id);
            $basketMapping[$key] = $basket->id;
            Cache::put($basket->customer_id, $basketMapping, self::CACHE_LIFETIME);
        }

        return $basket;
    }

    public function dropGuestBasket(Basket $basket): bool
    {
        return Cache::forget($basket->id);
    }

    /**
     * Создать/изменить/удалить товар временной корзины
     */
    public function setItem(Basket $basket, int $offerId, array $data): bool
    {
        $this->dropGuestBasket($basket);

        $item = $this->itemByOffer($basket, $offerId, $data['bundle_id'] ?? null, $data['bundle_item_id'] ?? null);
        $itemIndex = $basket->items->search(fn($savedItem) => $savedItem->id === $item->id);
        if ($item->id && isset($data['qty']) && !$data['qty']) {
            $basket->items->forget($itemIndex);
        } else {
            if (isset($data['qty']) && $data['qty'] > 0) {
                $item->qty = $data['qty'];
            }
            if (array_key_exists('referrer_id', $data) && !$data['referrer_id']) {
                unset($data['referrer_id']);
            }

            $item->fill($data);
            $item->setDataByType($data);

            if ($basket->items->contains('id', $item->id)) {
                $basket->items->transform(function ($savedBasketItem) use ($item) {
                    if ($savedBasketItem->id !== $item->id) {
                        return $savedBasketItem;
                    }

                    return $item;
                });
            } else {
                $basket->items->push($item);
            }
        }

        Cache::put($basket->id, $basket,self::CACHE_LIFETIME);
        Log::debug(json_encode([
            'method' => 'setItem',
            'basket' => $basket
        ]));

        return true;
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
            $item->id = random_int(round(10 ** 7), round(99 ** 7));
            $item->offer_id = $offerId;
            $item->basket_id = $basket->id;
            $item->type = $basket->type;
        }

        return $item;
    }
}
