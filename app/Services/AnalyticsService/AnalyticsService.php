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
        self::STATUS_ACCEPTED,
        self::STATUS_SHIPPED,
        self::STATUS_TRANSITION,
        self::STATUS_DONE,
        self::STATUS_CANCELED,
        self::STATUS_RETURNED,
    ];

    const STATUS_ACCEPTED = 'accepted';
    const STATUS_SHIPPED = 'shipped';
    const STATUS_TRANSITION = 'transition';
    const STATUS_DONE = 'done';
    const STATUS_CANCELED = 'canceled';
    const STATUS_RETURNED = 'returned';

    /** @throws Exception */
    public function getCountedByStatusProductItemsForPeriod(int $merchantId, string $start, string $end): ?array
    {
        $interval = new AnalyticsDateInterval($start, $end);
        /** @var Shipment[]|Collection $shipments */
        $shipments = Shipment::with(['basketItems' => fn(BelongsToMany $relation) =>
            $relation->selectRaw('price*qty as sum')
                ->where('is_returned', false)
        ])
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
            $currentData[$status]['lfl'] = $this->lfl($currentData[$status]['sum'], $datum['sum']);
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
                    $result[$status]['sum'] += $shipment->basketItems->sum('sum');
                }
            }
        }
        return $result;
    }


    /** @throws Exception */
    public function getMerchantSalesAnalytics(int $merchantId, string $start, string $end, string $intervalType = AnalyticsDateInterval::TYPE_MONTH): array
    {
        $groupBy = AnalyticsDateInterval::TYPES[$intervalType]['groupBy'];
        $interval = new AnalyticsDateInterval($start, $end, $intervalType);
        /** @var SimpleCollection|Collection[] $shipments */
        $shipments = Shipment::with(['basketItems' => fn($query) => $query->selectRaw('price*qty as sum')->where('is_returned', false)])
            ->whereHas('basketItems', fn($query) => $query->where('is_returned', false))
            ->where('merchant_id', $merchantId)
            ->whereBetween('status_at', $interval->fullPeriod())
            ->where('status', ShipmentStatus::DONE)
            ->where('is_canceled', false)
            ->addSelect([
                'id',
                'merchant_id',
                'status',
                DB::raw('YEAR(status_at) year'),
                DB::raw('MONTH(status_at) month'),
                DB::raw('DAY(status_at) day')]
            )
            ->get()->groupBy([$groupBy]);

        $result = [];
        foreach ($shipments as $intervalNumber => $intervalShipments) {
            $salesSumCallback = fn(Shipment $shipment) => (int)$shipment->basketItems->sum('sum');
            $current = $intervalShipments->where($intervalType, $interval->previousEnd->{$intervalType});
            $previous = $intervalShipments->where($intervalType, $interval->end->{$intervalType});
            $previousSum = $previous->sum($salesSumCallback);
            $currentSum = $current->sum($salesSumCallback);
            $result['current'][] = [
                'intervalNumber' => $intervalNumber,
                'sum' => $currentSum,
            ];
            $result['previous'][] = [
                'intervalNumber' => $intervalNumber,
                'sum' => $previousSum,
            ];
        }
        return $result;
    }

    /** @throws Exception */
    public function getMerchantTopProducts(int $merchantId, string $start, string $end, int $limit = 10): SimpleCollection
    {
        $interval = new AnalyticsDateInterval($start, $end, AnalyticsDateInterval::TYPE_MONTH);
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
                'count' => $productItems->sum('qty'),
                'lfl' => $this->lfl($currentSum, $prevSum),
            ]);
        }
        return $result->sortByDesc('sum')->values()->slice(0, $limit);
    }

    private function lfl(int $currentSum, int $prevSum): int
    {
        if ($prevSum === 0) {
            return 100;
        }
        $diff = ($currentSum - $prevSum);
        return (int) ( ($diff / $prevSum) * 100);
    }

    private function shipmentQuery(array $period, int $merchantId): \Closure
    {
        return fn(Builder $query) => $query
            ->where('merchant_id', $merchantId)
            ->whereBetween('created_at', $period);
    }

    private function simpleStatusCheck(Shipment $shipment, string $status): bool
    {
        if (!$shipment->is_canceled || $status === self::STATUS_CANCELED) {
            switch ($shipment->status) {
                case $status === self::STATUS_SHIPPED:
                    return $shipment->status === ShipmentStatus::SHIPPED;
                case $status === self::STATUS_TRANSITION:
                    return $shipment->status >= ShipmentStatus::ON_POINT_IN && $shipment->status <= ShipmentStatus::DELIVERING;
                case $status === self::STATUS_DONE:
                    return $shipment->status === ShipmentStatus::DONE;
                case $status === self::STATUS_CANCELED:
                    return $shipment->status >= ShipmentStatus::AWAITING_CONFIRMATION && $shipment->is_canceled;
                case $status === self::STATUS_RETURNED:
                    return $shipment->status >= ShipmentStatus::CANCELLATION_EXPECTED;
                case $status === self::STATUS_ACCEPTED:
                    return $shipment->status >= ShipmentStatus::AWAITING_CONFIRMATION;
            }
        }
        return false;
    }
}
