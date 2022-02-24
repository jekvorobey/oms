<?php

namespace App\Services\BasketService;

use App\Models\Basket\Basket;
use Exception;

/**
 * Класс-бизнес логики по работе с корзинами
 * Class CustomerBasketService
 * @package App\Services
 */
class CustomerBasketService extends BasketService
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
     * Создать/изменить/удалить товар корзины
     * @param array $data
     * @return bool|null
     * @throws Exception
     */
    public function setItem(Basket $basket, int $offerId, array $data): bool
    {
        $item = $this->itemByOffer($basket, $offerId, $data['bundle_id'] ?? null, $data['bundle_item_id'] ?? null);
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

            if ($item->wasRecentlyCreated) {
                $basket->items->push($item);
            }
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
