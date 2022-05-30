<?php

namespace App\Services\AnalyticsService;

use App\Http\Requests\AnalyticsRequest;
use App\Http\Requests\AnalyticsTopRequest;
use App\Models\Basket\BasketItem;
use App\Models\Delivery\Shipment;
use App\Models\Delivery\ShipmentStatus;
use Carbon\Carbon;
use Exception;
use Greensight\Store\Dto\StockHistoryDto;
use Greensight\Store\Services\StockService\StockService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;

class AnalyticsService
{
    public const SHIPMENT_STATUS_GROUPS = [
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
    public function getCountedByStatusProductItemsForPeriod(AnalyticsRequest $request): ?array
    {
        $interval = new AnalyticsDateInterval($request->start, $request->end);

        /** @var Shipment[]|EloquentCollection $shipments */
        $shipments = Shipment::with([
            'basketItems' => fn(BelongsToMany $relation) => $relation->selectRaw('price*qty as sum, qty')
                ->where('is_returned', false),
        ])
            ->select(['id', 'status', 'created_at', 'is_canceled'])
            ->where('merchant_id', $request->merchantId)
            ->whereBetween('created_at', $interval->fullPeriod())
            ->where('status', '>=', ShipmentStatus::AWAITING_CONFIRMATION)
            ->orderBy('created_at')
            ->get();

        $previousPeriodShipments = $shipments->filter(fn(Shipment $shipment) => $interval->isDateWithinPreviousPeriod($shipment->created_at));
        $currentPeriodShipments = $shipments->filter(fn(Shipment $shipment) => $interval->isDateWithinCurrentPeriod($shipment->created_at));

        $currentData = $this->groupedByStatusCalculatedShipments($currentPeriodShipments);
        $previousData = $this->groupedByStatusCalculatedShipments($previousPeriodShipments);

        foreach ($previousData as $status => $previousDataItem) {
            $currentData[$status]['lfl'] = $this->lfl($currentData[$status]['sum'], $previousDataItem['sum']);
        }

        return $currentData;
    }

    private function groupedByStatusCalculatedShipments(EloquentCollection $shipments): array
    {
        $defaultAssocArray = [
            'sum' => 0,
            'countShipments' => 0,
            'countProducts' => 0,
        ];

        $result = array_map(fn() => $defaultAssocArray, array_flip(self::SHIPMENT_STATUS_GROUPS));

        foreach ($shipments as $shipment) {
            /** @var Shipment $shipment */
            if ($statusGroup = $this->getShipmentStatusGroup($shipment)) {
                $this->fillShipmentProductData($result[$statusGroup], $shipment);

                if ($statusGroup !== self::STATUS_ACCEPTED) {
                    $this->fillShipmentProductData($result[self::STATUS_ACCEPTED], $shipment);
                }
            }
        }

        return $result;
    }

    private function getShipmentStatusGroup(Shipment $shipment): ?string
    {
        $shipmentStatus = (int) $shipment->status;

        switch (true) {
            case $shipmentStatus >= ShipmentStatus::AWAITING_CONFIRMATION && $shipment->is_canceled:
                return self::STATUS_CANCELED;
            case $shipmentStatus === ShipmentStatus::SHIPPED:
                return self::STATUS_SHIPPED;
            case $shipmentStatus >= ShipmentStatus::ON_POINT_IN && $shipmentStatus <= ShipmentStatus::DELIVERING:
                return self::STATUS_TRANSITION;
            case $shipmentStatus === ShipmentStatus::DONE:
                return self::STATUS_DONE;
            case $shipmentStatus >= ShipmentStatus::CANCELLATION_EXPECTED:
                return self::STATUS_RETURNED;
            case $shipmentStatus >= ShipmentStatus::AWAITING_CONFIRMATION:
                return self::STATUS_ACCEPTED;
            default:
                return null;
        }
    }

    private function fillShipmentProductData(&$data, Shipment $shipment): void
    {
        $data['countShipments']++;
        $data['countProducts'] += $shipment->basketItems->sum('qty');
        $data['sum'] += $shipment->basketItems->sum('sum');
    }

    /** @throws Exception */
    public function getMerchantSalesAnalytics(AnalyticsRequest $request): array
    {
        $interval = new AnalyticsDateInterval($request->start, $request->end);

        /** @var Collection|EloquentCollection[] $shipments */
        $shipments = Shipment::with([
            'basketItems' => fn($query) => $query->selectRaw('price*qty as sum')->where('is_returned', false),
        ])
            ->whereHas('basketItems', fn($query) => $query->where('is_returned', false))
            ->where('merchant_id', $request->merchantId)
            ->whereBetween('status_at', $interval->fullPeriod())
            ->where('status', ShipmentStatus::DONE)
            ->where('is_canceled', false)
            ->select([
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

        /** @var EloquentCollection[] $shipmentGroups */
        $shipmentGroups = [];

        $shipmentGroups['current'] = $shipments
            ->filter(fn(Shipment $shipment) => $interval->isDateWithinCurrentPeriod(Carbon::parse($shipment->status_at)))
            ->mapToGroups($intervalCallback);

        $shipmentGroups['previous'] = $shipments
            ->filter(fn(Shipment $shipment) => $interval->isDateWithinPreviousPeriod(Carbon::parse($shipment->status_at)))
            ->mapToGroups($intervalCallback);

        $result = [];

        foreach ($shipmentGroups as $period => $shipmentGroup) {
            $result[$period] = $shipmentGroup->map(function (EloquentCollection $shipmentItems, $intervalNumber) {
                /** @param EloquentCollection|Shipment[] $shipments */
                return [
                    'intervalNumber' => $intervalNumber,
                    'sum' => $shipmentItems->sum(fn(Shipment $shipment) => (int) $shipment->basketItems->sum('sum')),
                ];
            });
        }

        return array_map(fn(Collection $items) => $items->sortBy('intervalNumber')->values(), $result);
    }

    /** @throws Exception */
    public function getMerchantBestsellers(AnalyticsTopRequest $request): Collection
    {
        $interval = new AnalyticsDateInterval($request->start, $request->end);
        $topProductsQuery = BasketItem::query()->select('id', 'offer_id', 'name', 'price', 'qty');

        $currentTopProductsQuery = (clone $topProductsQuery)
            ->whereHas('shipmentItem.shipment', $this->shipmentQuery($interval->currentPeriod(), $request->merchantId))
            ->limit($request->limit);
        /** @var BasketItem[]|EloquentCollection $currentTopProducts */
        $currentTopProducts = $currentTopProductsQuery->get();
        $currentGroupedTopProducts = $currentTopProducts->groupBy('offer_id');

        $previousTopProductsQuery = (clone $topProductsQuery)
            ->whereHas('shipmentItem.shipment', $this->shipmentQuery($interval->previousPeriod(), $request->merchantId))
            ->whereIn('offer_id', $currentTopProducts->pluck('offer_id'))
            ->limit($request->limit);
        $previousTopProducts = $previousTopProductsQuery->get();

        /** @var EloquentCollection $previousGroupedTopProducts */
        $previousGroupedTopProducts = $previousTopProducts->groupBy('offer_id');

        $result = collect([]);
        /** @var EloquentCollection|BasketItem[] $productItems */
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

        return $result->sortByDesc('sum')->values()->slice(0, $request->limit);
    }

    /** @throws Exception */
    public function getProductsTurnover(AnalyticsTopRequest $request, bool $descending = false): Collection
    {
        $interval = new AnalyticsDateInterval($request->start, $request->end);

        /** @var Builder|BelongsTo $query */
        $shipmentQueryCallback = fn($query) => $query
            ->where('merchant_id', $request->merchantId)
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
            ->where('qty', '>', 0)
            ->whereHas('shipmentItem.shipment', $shipmentQueryCallback)
            ->get()
            ->groupBy('offer_id');

        $stockHistory = $this->getStockHistory($request);

        $periodDays = $interval->currentPeriodDays();
        $result = collect();
        foreach ($groupedBasketItems as $offerId => $basketItemOfferGroup) {
            if (!$stockHistory->has($offerId)) {
                continue;
            }

            $averageStock = $this->calculateAverageStock($stockHistory->get($offerId));

            $result->push([
                'name' => $basketItemOfferGroup->first()['name'],
                'days' => round($averageStock / $basketItemOfferGroup->sum('qty') * $periodDays),
            ]);
        }

        return $result->sortBy('days', SORT_REGULAR, $descending)->take($request->limit)->values();
    }

    private function getStockHistory(AnalyticsTopRequest $request): Collection
    {
        /** @var StockService $stockService */
        $stockService = resolve(StockService::class);
        $stockHistoryQuery = $stockService->newQuery()
            ->addFields('offer_id', 'qty')
            ->setFilter('date', '>=', $request->start)
            ->setFilter('date', '<=', $request->end)
            ->addSort('date');

        return $stockService->history($stockHistoryQuery, $request->merchantId)
            ->groupBy('offer_id');
    }

    /**
     * @param Collection|StockHistoryDto[] $offerStockHistory
     */
    private function calculateAverageStock(Collection $offerStockHistory): float
    {
        if ($offerStockHistory->isEmpty()) {
            return 0;
        }

        if ($offerStockHistory->count() === 1) {
            return $offerStockHistory->first()->qty;
        }

        /** @var StockHistoryDto $first */
        $first = $offerStockHistory->first();
        /** @var StockHistoryDto $first */
        $last = $offerStockHistory->last();

        $n = $offerStockHistory->count();
        $sliced = $offerStockHistory->slice(1, -1);
        $sum = ($first->qty / 2) + $sliced->sum('qty') + ($last->qty / 2);

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

    private function shipmentQuery(array $period, int $merchantId): callable
    {
        return fn($query) => $query
            ->where('merchant_id', $merchantId)
            ->whereBetween('created_at', $period);
    }
}
