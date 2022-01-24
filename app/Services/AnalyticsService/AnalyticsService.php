<?php

namespace App\Services\AnalyticsService;

use App\Models\Basket\BasketItem;
use App\Models\Delivery\Shipment;
use App\Models\Delivery\ShipmentStatus;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection as SimpleCollection;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    public const SIMPLIFIED_STATUSES = [
        'accepted',
        'shipped',
        'transition',
        'done',
        'canceled',
        'returned',
    ];

    /** @throws Exception */
    public function getCountedByStatusProductItemsForPeriod(int $merchantId, string $start, string $end): ?array
    {
        $interval = new DoubleDateInterval($start, $end);
        /** @var Shipment[]|Collection $shipments */
        $shipments = Shipment::with(['basketItems' => fn(BelongsToMany $relation) => $relation->select(['price', 'qty'])])
            ->select(['id', 'status', 'created_at'])
            ->where('merchant_id', $merchantId)
            ->whereBetween('created_at', $interval->fullPeriod())
            ->orderBy('created_at')
            ->get();
        $previousPeriodShipments = $shipments->filter(fn(Shipment $shipment) => $interval->isDateWithinPreviousPeriod($shipment->created_at));
        $currentPeriodShipments = $shipments->filter(fn(Shipment $shipment) => $interval->isDateWithinCurrentPeriod($shipment->created_at));

        $currentData = $this->groupedByStatusCalculatedShipments($currentPeriodShipments);
        $previousData = $this->groupedByStatusCalculatedShipments($previousPeriodShipments, false);
        foreach ($previousData as $status => $datum) {
            $currentData[$status]['lfl'] = $datum['sum'] ? (int)(($currentData[$status]['sum'] - $datum['sum']) / $datum['sum'] * 100) : 100;
        }

        return $currentData;
    }

    private function groupedByStatusCalculatedShipments(Collection $shipments, $count = true): array
    {
        $defaultAssocArray = [
            'sum' => 0,
        ];
        if ($count) {
            $defaultAssocArray += [
                'countShipments' => 0,
                'countProducts' => 0,
            ];
        }
        $result = array_map(fn() => $defaultAssocArray, array_flip(self::SIMPLIFIED_STATUSES));

        foreach ($shipments as $shipment) {
            foreach (self::SIMPLIFIED_STATUSES as $status) {
                if ($this->simpleStatusCheck($shipment, $status)) {
                    if ($count) {
                        $result[$status]['countShipments']++;
                        $result[$status]['countProducts'] += $shipment->basketItems->count();
                    }
                    $result[$status]['sum'] += $shipment->basketItems->sum(fn(BasketItem $item) => (int)$item->price * $item->qty);
                }
            }
        }
        return $result;
    }


    /** @throws Exception */
    public function getMerchantSalesAnalytics(int $merchantId, string $start, string $end): array
    {
        $interval = new DoubleDateInterval($start, $end);
        /** @var SimpleCollection|Collection[] $shipments */
        $shipments = Shipment::with(['basketItems' => fn($query) => $query->selectRaw('price*qty as sum')])
            ->whereHas('basketItems', fn($query) => $query->where('is_returned', false))
            ->where('merchant_id', $merchantId)
            ->whereBetween('status_at', $interval->fullPeriod())
            ->where('status', ShipmentStatus::DONE)
            ->where('is_canceled', false)
            ->addSelect(['id', 'merchant_id', 'status', DB::raw('YEAR(created_at) year'), DB::raw('MONTH(created_at) month')])
            ->get()->groupBy('year');

        $result = [];
        foreach ($shipments as $year => $yearShipments) {
            $shipments[$year] = $shipments[$year]->groupBy('month')->sortKeys();
            /** @var Collection $monthShipments */
            foreach ($shipments[$year] as $month => $monthShipments) {
                $sum = $monthShipments->sum(fn(Shipment $shipment) => (int)$shipment->basketItems->sum(
                    fn(BasketItem $item) => (int)$item['sum']
                ));

                $result[$year][] = [
                    'month' => $month,
                    'sum' => $sum,
                ];
            }
        }

        return $result;
    }

    /** @throws Exception */
    public function getMerchantTopProducts(int $merchantId, string $start, string $end, int $limit = 10): SimpleCollection
    {
        $interval = new DoubleDateInterval($start, $end, DoubleDateInterval::TYPE_MONTH);
        $topProductsQuery = BasketItem::query()->select('id', 'offer_id', 'name', 'price', 'qty');

        /** @var BasketItem[]|Collection $currentTopProducts */
        /** @var Collection|Collection[] $currentGroupedTopProducts */
        $currentTopProductsQuery = (clone $topProductsQuery)
            ->whereHas('shipmentItem.shipment', $this->shipmentQuery($interval->currentPeriod(), $merchantId));
        $currentTopProducts = $currentTopProductsQuery->get();
        $currentGroupedTopProducts = $currentTopProducts->groupBy('offer_id');
        $previousTopProductsQuery = (clone $topProductsQuery)->whereHas('shipmentItem.shipment', $this->shipmentQuery($interval->previousPeriod(), $merchantId))
            ->whereIn('offer_id', $currentTopProducts->pluck('offer_id')->unique());

        $previousTopProducts = $previousTopProductsQuery->get();

        /** @var Collection $previousGroupedTopProducts */
        $previousGroupedTopProducts = $previousTopProducts->groupBy('offer_id');

        $result = collect([]);
        /** @var Collection|BasketItem[] $productItems */
        foreach ($currentGroupedTopProducts as $offerId => $productItems) {
            $sumCallback = fn(BasketItem $item) => (int)($item->price * $item->qty);
            $prevSum = isset($previousGroupedTopProducts[$offerId]) ? $previousGroupedTopProducts[$offerId]->sum($sumCallback) : 0;
            $result->push([
                'name' => $productItems[0]->name,
                'offerId' => $offerId,
                'sum' => $currentSum = $productItems->sum($sumCallback),
                'count' => $productItems->sum(fn (BasketItem $item) => (int) $item->qty),
                'lfl' => $prevSum ? $this->lfl($currentSum, $prevSum) : 100,
            ]);
        }
        return $result->sortByDesc('sum')->values()->slice(0, $limit);
    }

    private function lfl(int $currentSum, int $prevSum) {
        $diff = ($currentSum - $prevSum);
        return (int) ( ($diff / $prevSum) * 100) ;
    }
    private function shipmentQuery(array $period, int $merchantId): \Closure
    {
        return fn(Builder $query) => $query
            ->where('merchant_id', $merchantId)
            ->whereBetween('created_at', $period);
    }

    private function simpleStatusCheck(Shipment $shipment, string $status): bool
    {
        if (!$shipment->is_canceled || $status === 'returned') {
            switch ($shipment->status) {
                case $status === 'shipped':
                    return $shipment->status === ShipmentStatus::SHIPPED;
                case $status === 'transition':
                    return $shipment->status >= ShipmentStatus::ON_POINT_IN && $shipment->status <= ShipmentStatus::DELIVERING;
                case $status === 'done':
                    return $shipment->status === ShipmentStatus::DONE;
                case $status === 'canceled':
                    return $shipment->status >= ShipmentStatus::AWAITING_CONFIRMATION && $shipment->is_canceled;
                case $status === 'returned':
                    return $shipment->status >= ShipmentStatus::CANCELLATION_EXPECTED;
                case $status === 'accepted':
                    return $shipment->status >= ShipmentStatus::AWAITING_CONFIRMATION;
            }
        }
        return false;
    }
}
