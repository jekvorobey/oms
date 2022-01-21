<?php

namespace App\Services;

use App\Models\Basket\Basket;
use App\Models\Basket\BasketItem;
use App\Models\Delivery\Shipment;
use App\Models\Delivery\ShipmentStatus;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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
        /** @var Shipment[]|Collection $shipments */
        $shipments = Shipment::with('basketItems')
            ->where('merchant_id', $merchantId)
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->get();

        $result = array_map(fn() => [
            'countShipments' => 0,
            'countProducts' => 0,
            'sum' => 0,
        ], Shipment::SIMPLIFIED_STATUSES);
        foreach ($shipments as $shipment) {
            foreach (array_keys($result) as $status) {
                if ($this->simpleStatusCheck($shipment, $status)) {
                    $result[$status]['countShipments']++;
                    $result[$status]['countProducts'] += $shipment->basketItems->count();
                    $result[$status]['sum'] += $shipment->basketItems->sum(fn(BasketItem $item) => (int) $item->price * $item->qty);
                }
            }
        }
        return $result;
    }

    /** @return  Shipment[]|Collection $shipments */
    public function getMerchantSalesAnalytics(int $merchantId, int $year)
    {
        $years = [$year - 1, $year];
        /** @var \Illuminate\Support\Collection|Collection[] $shipments */
        $shipments = Shipment::with('basketItems')
            ->where('merchant_id', $merchantId)
            ->whereBetween(DB::raw('YEAR(created_at)'), $years)
//            ->where('status', ShipmentStatus::DONE)
//            ->where('is_canceled', false)
            ->addSelect(['id', 'merchant_id', 'status', DB::raw('YEAR(created_at) year'), DB::raw('MONTH(created_at) month')])
            ->get()->mapToGroups(fn(Shipment $shipment) => [
                $shipment->year => $shipment,
            ]);

//        dd($yearShipments);
        foreach ($shipments->keys() as $year) {
            $shipments[$year] = $shipments[$year]->mapToGroups(fn(Shipment $shipment) => [
                $shipment->month => $shipment,
            ])->sortKeys();
            /** @var Collection $monthShipments */
            foreach ($shipments[$year] as $month => $monthShipments) {
                $sum = $monthShipments->sum(fn(Shipment $shipment) => (int) $shipment->basketItems->sum(
                    fn(BasketItem $item) => (int) $item->price * $item->qty
                ));

                $shipments[$year][$month] = [
                    'month' => $month,
                    'sum' => $sum,
                ];
            }
        }

        return $shipments;
    }

    public function getMerchantTopProducts(int $merchantId)
    {
        $res = BasketItem::query()
            ->whereHas('shipmentItem.shipment', fn(Builder $query) => $query
                ->where('merchant_id', $merchantId))
            ->selectRaw('name, SUM(price*qty) as sum, SUM(qty) as count')
            ->groupBy(['name'])
            ->orderByDesc('sum')
        ;
        return $res->get()
//            ->map(function (BasketItem $item) {
//            $item->sum = (int) $item->sum;
//            $item->count = (int) $item->count;
//            return $item;
//        })
            ;
    }

    public function simpleStatusCheck(Shipment $shipment, string $status): bool
    {
        $result = false;
        switch ($shipment->status) {
            case $status === 'shipped':
                $result = $shipment->status === ShipmentStatus::SHIPPED;
                break;
            case $status === 'transition':
                $result = $shipment->status >= ShipmentStatus::ON_POINT_IN && $shipment->status <= ShipmentStatus::DELIVERING;
                break;
            case $status === 'done':
                $result = $shipment->status === ShipmentStatus::DONE;
                break;
            case $status === 'canceled':
                $result = $shipment->status >= ShipmentStatus::AWAITING_CONFIRMATION && $shipment->is_canceled;
                break;
            case $status === 'returned':
                $result = $shipment->status >= ShipmentStatus::CANCELLATION_EXPECTED;
                break;
            case $status === 'accepted':
                $result = $shipment->status >= ShipmentStatus::AWAITING_CONFIRMATION;
                break;
        }
        return $result;
    }
}
