<?php

namespace App\Services;

use App\Models\Basket\Basket;
use App\Models\Basket\BasketItem;
use App\Models\Delivery\Shipment;
use Exception;
use Illuminate\Support\Facades\DB;

/**
 * Класс-бизнес логики по работе с корзинами
 * Class BasketService
 * @package App\Services
 */
class BasketService
{

    public const SUM_PREFIX = 'sum';
    /**
     * Получить объект корзины по его id
     */
    public function getBasket(int $basketId): ?Basket
    {
        return Basket::find($basketId);
    }

    /**
     * Получить текущую корзину пользователя
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
    public function itemByOffer(int $basketId, int $offerId, ?int $bundleId = null): ?BasketItem
    {
        $basket = $this->getBasket($basketId);
        if (is_null($basket)) {
            return null;
        }

        $item = $basket->items->first(function (BasketItem $item) use ($offerId, $bundleId) {
            return $bundleId ?
                $item->offer_id == $offerId && $item->bundle_id == $bundleId :
                $item->offer_id == $offerId && is_null($item->bundle_id);
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

    public function getCountedByStatusProductItemsForPeriod(int $merchantId, int $year, int $month): ?array
    {
        $sumPrefix = self::SUM_PREFIX;
        $query = DB::table('basket_items AS bi')
            ->join('shipment_items AS si', 'si.basket_item_id', '=', 'bi.id')
            ->join('shipments AS s', 'si.shipment_id', '=', 's.id')
            ->where('s.merchant_id', $merchantId)
            ->where(DB::raw('YEAR(s.created_at)'), $year)
//            ->where(DB::raw('MONTH(s.created_at)'), $month)
            ->selectRaw(Shipment::aggregatedQueryString('SUM', 'bi.qty') . ', ' . Shipment::aggregatedQueryString('SUM', 'bi.price*bi.qty', $sumPrefix))
            ->groupBy(['merchant_id']);


        $dbResult = (array) $query->first();
        $result = [];
        if (count($dbResult)) {
            foreach (array_keys(Shipment::SIMPLIFIED_STATUSES) as $status) {
                $result[$status]['count'] = (int) $dbResult[$status];
                $result[$status][$sumPrefix] = (int) $dbResult["{$sumPrefix}_$status"];
            }
        } else {
            return null;
        }

        return $result;
    }
}
