<?php

namespace App\Services\BasketService;

use App\Models\Basket\Basket;
use Http\Discovery\Exception\NotFoundException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GuestBasketService extends BasketService
{
    private const CACHE_LIFETIME = 7 * 24 * 60;

    public function getBasket(int $basketId): Basket
    {
        $basket = Cache::get($basketId);

        if (!$basket) {
            throw new \RuntimeException('Guest basket not found');
        }

        return $basket;
    }

    public function findFreeUserBasket(int $type, $customerId): Basket
    {
        $basketMappings = Cache::get($customerId);
        $key = $this->getBasketKey($type);

        if (isset($basketMappings[$key])) {
            $basketUuid = $basketMappings[$key];
            $basket = Cache::get($basketUuid);
        } else {
            $basket = $this->createBasket($type, $customerId);
        }

        return $basket;
    }

    protected function createBasket(int $type, $customerId): Basket
    {
        $basket = new Basket();
        $basket->id = $this->generateId();
        $basket->customer_id = $customerId;
        $basket->type = $type;
        $basket->is_belongs_to_order = false;

        Cache::put($basket->id, $basket, self::CACHE_LIFETIME);
        $basketMapping = Cache::get($basket->customer_id);

        $key = $this->getBasketKey($type);

        if ($key) {
            Cache::forget($basket->customer_id);
            $basketMapping[$key] = $basket->id;
            Cache::put($basket->customer_id, $basketMapping, self::CACHE_LIFETIME);
        }

        return $basket;
    }

    public function deleteBasket(Basket $basket): bool
    {
        return Cache::forget($basket->id);
    }

    /**
     * Создать/изменить/удалить товар временной корзины
     */
    public function setItem(Basket $basket, int $offerId, array $data): bool
    {
        $this->deleteBasket($basket);

        $item = $this->itemByOffer($basket, $offerId, $data['bundle_id'] ?? null, $data['bundle_item_id'] ?? null);

        if (!$item->id) {
            $item->id = $this->generateId();
        }

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

            if ($itemIndex !== false) {
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

        Cache::put($basket->id, $basket, self::CACHE_LIFETIME);

        Log::debug(json_encode($basket));

        return true;
    }

    private function getBasketKey(int $type): string
    {
        switch ($type) {
            case Basket::TYPE_PRODUCT:
                return 'product';
            case Basket::TYPE_CERTIFICATE:
                return 'certificate';
            case Basket::TYPE_MASTER:
                return 'master';
            default:
                throw new NotFoundException("Type of basket $type not found");
        }
    }

    private function generateId(): int
    {
        return random_int(
            round(10 ** 7),
            round(99 ** 7)
        );
    }
}
