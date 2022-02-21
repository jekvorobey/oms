<?php

namespace App\Services\AnalyticsService;

use App\Models\Basket\BasketItem;
use App\Models\Delivery\Shipment;
use App\Models\Delivery\ShipmentStatus;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection as SimpleCollection;
use Illuminate\Database\Eloquent\Builder;

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

    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_SHIPPED = 'shipped';
    public const STATUS_TRANSITION = 'transition';
    public const STATUS_DONE = 'done';
    public const STATUS_CANCELED = 'canceled';
    public const STATUS_RETURNED = 'returned';

    /** @throws Exception */
    public function getCountedByStatusProductItemsForPeriod(int $merchantId, string $start, string $end): ?array
    {
        $interval = new AnalyticsDateInterval($start, $end);
        /** @var Shipment[]|Collection $shipments */
        $shipments = Shipment::with([
            'basketItems' => fn(BelongsToMany $relation) => $relation->selectRaw('price*qty as sum, qty')
                ->where('is_returned', false),
        ])
            ->select(['id', 'status', 'created_at', 'is_canceled'])
            ->where('merchant_id', $merchantId)
            ->whereBetween('created_at', $interval->fullPeriod())
            ->orderBy('created_at')
            ->get();
        $previousPeriodShipments = $shipments->filter(fn(Shipment $shipment) => $interval->isDateWithinPreviousPeriod($shipment->created_at));
        $currentPeriodShipments = $shipments->filter(fn(Shipment $shipment) => $interval->isDateWithinCurrentPeriod($shipment->created_at));
        $currentData = $this->groupedByStatusCalculatedShipments($currentPeriodShipments);
        $previousData = $this->groupedByStatusCalculatedShipments($previousPeriodShipments);

        foreach ($previousData as $status => $datum) {
            $currentData[$status]['lfl'] = $this->lfl($currentData[$status]['sum'], $datum['sum']);
        }
        return $currentData;
    }

    private function groupedByStatusCalculatedShipments(Collection $shipments): array
    {
        $defaultAssocArray = [
            'sum' => 0,
            'countShipments' => 0,
            'countProducts' => 0,
        ];

        $result = array_map(fn() => $defaultAssocArray, array_flip(self::SIMPLIFIED_STATUSES));

        foreach ($shipments as $shipment) {
            /** @var Shipment $shipment */
            if ($status = $this->getSimpleStatus($shipment)) {
                $this->fillShipmentProductData($result[$status], $shipment);
                if ($status !== self::STATUS_ACCEPTED) {
                    $this->fillShipmentProductData($result[self::STATUS_ACCEPTED], $shipment);
                }
            }
        }
        return $result;
    }

    private function fillShipmentProductData(&$data, Shipment $shipment)
    {
        $data['countShipments']++;
        $data['countProducts'] += $shipment->basketItems->sum('qty');
        $data['sum'] += $shipment->basketItems->sum('sum');
    }

    /** @throws Exception */
    public function getMerchantSalesAnalytics(int $merchantId, string $start, string $end): array
    {
        $interval = new AnalyticsDateInterval($start, $end);
        /** @var SimpleCollection|Collection[] $shipments */
        $shipments = Shipment::with([
            'basketItems' => fn($query) => $query->selectRaw('price*qty as sum')->where('is_returned', false),
        ])
            ->whereHas('basketItems', fn($query) => $query->where('is_returned', false))
            ->where('merchant_id', $merchantId)
            ->whereBetween('status_at', $interval->fullPeriod())
            ->where('status', ShipmentStatus:: DONE)
            ->where('is_canceled', false)
            ->addSelect([
                'id',
                'merchant_id',
                'status',
                'is_canceled',
                'status_at',
            ])
            ->orderBy('status_at')
            ->get();

        $intervalCallback = fn(Shipment $shipment) => [
            Carbon::parse($shipment->status_at)->format('Y-m-d') => $shipment,
        ];

        /** @var Collection[] $shipmentGroups */
        $shipmentGroups = [];

        $shipmentGroups['current'] = $shipments
            ->filter(fn(Shipment $shipment) => $interval->isDateWithinCurrentPeriod(Carbon::parse($shipment->status_at)))
            ->mapToGroups($intervalCallback);

        $shipmentGroups['previous'] = $shipments
            ->filter(fn(Shipment $shipment) => $interval->isDateWithinPreviousPeriod(Carbon::parse($shipment->status_at)))
            ->mapToGroups($intervalCallback);

        $result = [];

        foreach ($shipmentGroups as $period => $shipmentGroup) {
            $result[$period] = $shipmentGroup->map(function (Collection $shipmentItems, $intervalNumber) {
                /** @param Collection|Shipment[] $shipments */
                return [
                    'intervalNumber' => $intervalNumber,
                    'sum' => $shipmentItems->sum(fn(Shipment $shipment) => (int) $shipment->basketItems->sum('sum')),
                ];
            });
        }
        return array_map(fn(SimpleCollection $items) => $items->sortBy('intervalNumber')->values(), $result);
    }

    /** @throws Exception */
    public function getMerchantBestsellers(int $merchantId, string $start, string $end, int $limit): SimpleCollection
    {
        $interval = new AnalyticsDateInterval($start, $end);
        $topProductsQuery = BasketItem::query()->select('id', 'offer_id', 'name', 'price', 'qty');

        $currentTopProductsQuery = (clone $topProductsQuery)
            ->whereHas('shipmentItem.shipment', $this->shipmentQuery($interval->currentPeriod(), $merchantId))
            ->limit($limit);
        /** @var BasketItem[]|Collection $currentTopProducts */
        $currentTopProducts = $currentTopProductsQuery->get();
        $currentGroupedTopProducts = $currentTopProducts->groupBy('offer_id');

        $previousTopProductsQuery = (clone $topProductsQuery)
            ->whereHas('shipmentItem.shipment', $this->shipmentQuery($interval->previousPeriod(), $merchantId))
            ->whereIn('offer_id', $currentTopProducts->pluck('offer_id'))
            ->limit($limit);
        $previousTopProducts = $previousTopProductsQuery->get();

        /** @var Collection $previousGroupedTopProducts */
        $previousGroupedTopProducts = $previousTopProducts->groupBy('offer_id');

        $result = collect([]);
        /** @var Collection|BasketItem[] $productItems */
        foreach ($currentGroupedTopProducts as $offerId => $productItems) {
            $sumCallback = function (BasketItem $item) {
                $sum = $item->price * $item->qty;
                return (int) $sum;
            };
            $prevSum = isset($previousGroupedTopProducts[$offerId]) ? $previousGroupedTopProducts[$offerId]->sum($sumCallback) : 0;
            $currentSum = $productItems->sum($sumCallback);
            $result->push([
                'name' => $productItems[0]->name,
                'offerId' => $offerId,
                'sum' => $currentSum,
                'count' => $productItems->sum('qty'),
                'lfl' => $this->lfl($currentSum, $prevSum),
            ]);
        }
        return $result->sortByDesc('sum')->values()->slice(0, $limit);
    }

    /** @throws Exception */
    public function getProductsTurnover(
        int $merchantId,
        string $start,
        string $end,
        int $limit,
        array $stockHistory,
        bool $descending = false
    ): SimpleCollection {
        $averageStocks = collect($stockHistory)->groupBy('offer_id')->map(fn(SimpleCollection $collection) => $this->calculateAverageStock($collection));
        $interval = new AnalyticsDateInterval($start, $end);
        /** @var Builder|BelongsTo $query */
        $shipmentQueryCallback = fn($query) => $query
            ->where('merchant_id', $merchantId)
            ->whereBetween('status_at', $interval->currentPeriod())
            ->where('status', ShipmentStatus::DONE)
            ->where('is_canceled', false)
            ->addSelect([
                'id',
                'merchant_id',
                'status',
                'is_canceled',
                'status_at',
            ])
            ->orderBy('status_at');

        $groupedBasketItems = BasketItem::query()->select('id', 'offer_id', 'name', 'qty')
            ->whereHas('shipmentItem.shipment', $shipmentQueryCallback)
            ->get()
            ->groupBy('offer_id')
        ;

        $periodDays = $interval->currentPeriodDays();
        $result = collect();
        foreach ($groupedBasketItems as $offerId => $basketItemOfferGroup) {
            if (isset($averageStocks[$offerId])) {
                $result->push([
                    'name' => $basketItemOfferGroup->first()['name'],
                    'days' => (int) round($averageStocks[$offerId] / $basketItemOfferGroup->sum('qty') * $periodDays),
                ]);
            }
        }

        return $result->sortBy('days', SORT_REGULAR, $descending)->take($limit)->values();
    }

    private function calculateAverageStock(SimpleCollection $collection)
    {
        $first = $collection->first();
        $last = $collection->last();
        $n = $collection->count();
        $sliced = $collection->slice(1, -1);
        $sum = ($first['qty'] / 2) + $sliced->sum('qty') + ($last['qty'] / 2);
        return $sum / ($n - 1);
    }

    private function lfl(int $currentSum, int $prevSum): int
    {
        if ($prevSum === 0) {
            return 0;
        }

        $diff = $currentSum - $prevSum;

        return (int) ($diff / $prevSum * 100);
    }

    private function shipmentQuery(array $period, int $merchantId): \Closure
    {
        return fn($query) => $query
            ->where('merchant_id', $merchantId)
            ->whereBetween('created_at', $period);
    }

    private function getSimpleStatus(Shipment $shipment): ?string
    {
        switch ($shipment->status) {
            case $shipment->status >= ShipmentStatus::AWAITING_CONFIRMATION && $shipment->is_canceled:
                return self::STATUS_CANCELED;
            case $shipment->status === ShipmentStatus::SHIPPED:
                return self::STATUS_SHIPPED;
            case $shipment->status >= ShipmentStatus::ON_POINT_IN && $shipment->status <= ShipmentStatus::DELIVERING:
                return self::STATUS_TRANSITION;
            case $shipment->status === ShipmentStatus::DONE:
                return self::STATUS_DONE;
            case $shipment->status >= ShipmentStatus::CANCELLATION_EXPECTED:
                return self::STATUS_RETURNED;
            case $shipment->status >= ShipmentStatus::AWAITING_CONFIRMATION:
                return self::STATUS_ACCEPTED;
        }
        return null;
    }
}
