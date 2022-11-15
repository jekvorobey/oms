<?php

namespace App\Services\AnalyticsService;

use App\Http\Requests\DashboardsAnalyticsRequest;
use App\Models\Basket\Basket;
use App\Models\Order\Order;
use App\Models\Payment\PaymentStatus;
use DateTime;
use Exception;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;

class DashboardsAnalyticsService
{
    public function salesAllPeriodByDay(): array
    {
        /** @var Order[]|EloquentCollection $shipments */
        $orders = Order::query()
            ->select([
                'orders.type as type',
                DB::raw('DATE_FORMAT(orders.created_at, \'%Y-%m-%d\') as date'),
                DB::raw('COUNT(DISTINCT orders.id) as countOrdersFull'),
                DB::raw('SUM(basket_items.price * basket_items.qty) as amountOrdersFull'),
                DB::raw('SUM(basket_items.qty) as countProductsFull'),
                DB::raw('COUNT(DISTINCT (CASE WHEN orders.payment_status IN (' . $this->paymentStatusCancel() . ') THEN NULL ELSE orders.id END)) AS countOrders'),
                DB::raw('SUM(CASE WHEN orders.payment_status IN (' . $this->paymentStatusCancel() . ') THEN NULL ELSE (basket_items.price * basket_items.qty) END) AS amountOrders'),
                DB::raw('SUM(CASE WHEN orders.payment_status IN (' . $this->paymentStatusCancel() . ') THEN NULL ELSE basket_items.qty END) AS countProducts'),
            ])
            ->leftJoin('basket_items', 'basket_items.basket_id', '=', 'orders.basket_id')
            ->groupBy('orders.type', 'date')
            ->orderBy('date')
            ->get();

        $results = [];
        foreach ($orders->toArray() as $item) {
            $item['nameType'] = $this->getOrderTypeName((int) $item['type']);
            $this->recalculation($item);

            $results[] = $item;
        }

        return $results;
    }

    /**
     * @throws Exception
     */
    public function salesDayByHour(DashboardsAnalyticsRequest $request): array
    {
        /** @var Order[]|EloquentCollection $shipments */
        $orders = Order::query()
            ->select([
                DB::raw('DATE_FORMAT(orders.created_at, \'%H\') as hour'),
                DB::raw('COUNT(DISTINCT orders.id) as countOrdersFull'),
                DB::raw('SUM(basket_items.price * basket_items.qty) as amountOrdersFull'),
                DB::raw('SUM(basket_items.qty) as countProductsFull'),
                DB::raw('COUNT(DISTINCT (CASE WHEN orders.payment_status IN (' . $this->paymentStatusCancel() . ') THEN NULL ELSE orders.id END)) AS countOrders'),
                DB::raw('SUM(CASE WHEN orders.payment_status IN (' . $this->paymentStatusCancel() . ') THEN NULL ELSE (basket_items.price * basket_items.qty) END) AS amountOrders'),
                DB::raw('SUM(CASE WHEN orders.payment_status IN (' . $this->paymentStatusCancel() . ') THEN NULL ELSE basket_items.qty END) AS countProducts'),
            ])
            ->leftJoin('basket_items', 'basket_items.basket_id', '=', 'orders.basket_id')
            ->where('orders.created_at', '>=', $request->start)
            ->where('orders.created_at', '<=', $request->end)
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        $results = [];
        for ($hour = 0; $hour <= 23; $hour++) {
            $hourFormatted = sprintf("%02d", $hour) . ':00';
            $item['hour'] = $hourFormatted;
            $this->recalculation($item);
            $results[$hourFormatted] = $item;
        }

        foreach ($orders->toArray() as $item) {
            $hourFormatted = sprintf("%02d", (int) $item['hour']) . ':00';
            $item['hour'] = $hourFormatted;
            $this->recalculation($item);
            $results[$hourFormatted] = $item;
        }

        $this->aggregate($results, true);

        if ((new DateTime($request->start))->format('Y-m-d') === (new DateTime())->format('Y-m-d')) {
            $this->clearAggregateByKey($results, (new DateTime())->format('H:00'));
        }

        return array_values($results);
    }

    /**
     * @throws Exception
     */
    public function salesMonthByDay(DashboardsAnalyticsRequest $request): array
    {
        /** @var Order[]|EloquentCollection $shipments */
        $orders = Order::query()
            ->select([
                DB::raw('DATE_FORMAT(orders.created_at, \'%Y-%m-%d\') as date'),
                DB::raw('COUNT(DISTINCT orders.id) as countOrdersFull'),
                DB::raw('SUM(basket_items.price * basket_items.qty) as amountOrdersFull'),
                DB::raw('SUM(basket_items.qty) as countProductsFull'),
                DB::raw('COUNT(DISTINCT (CASE WHEN orders.payment_status IN (' . $this->paymentStatusCancel() . ') THEN NULL ELSE orders.id END)) AS countOrders'),
                DB::raw('SUM(CASE WHEN orders.payment_status IN (' . $this->paymentStatusCancel() . ') THEN NULL ELSE (basket_items.price * basket_items.qty) END) AS amountOrders'),
                DB::raw('SUM(CASE WHEN orders.payment_status IN (' . $this->paymentStatusCancel() . ') THEN NULL ELSE basket_items.qty END) AS countProducts'),
            ])
            ->leftJoin('basket_items', 'basket_items.basket_id', '=', 'orders.basket_id')
            ->where('orders.created_at', '>=', $request->start)
            ->where('orders.created_at', '<=', $request->end)
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $results = [];

        $start = new DateTime($request->start);
        $end = new DateTime($request->end);
        for($dt = $start; $dt <= $end; $dt->modify('+1 day')){
            $date = $dt->format("Y-m-d");
            $item['date'] = $date;
            $this->recalculation($item);
            $results[$date] = $item;
        }

        foreach ($orders->toArray() as $item) {
            $date = $item['date'];
            $this->recalculation($item);
            $results[$date] = $item;
        }

        $this->aggregate($results, true);

        if ((new DateTime($request->start))->format('Y-m-d') === (new DateTime())->format('Y-m-d')) {
            $this->clearAggregateByKey($results, (new DateTime())->format('Y-m-d'));
        }

        return array_values($results);
    }

    /**
     * @throws Exception
     */
    public function salesYearByMonth(DashboardsAnalyticsRequest $request): array
    {
        /** @var Order[]|EloquentCollection $shipments */
        $orders = Order::query()
            ->select([
                DB::raw('DATE_FORMAT(orders.created_at, \'%Y-%m-01\') as month'),
                DB::raw('COUNT(DISTINCT orders.id) as countOrdersFull'),
                DB::raw('SUM(basket_items.price * basket_items.qty) as amountOrdersFull'),
                DB::raw('SUM(basket_items.qty) as countProductsFull'),
                DB::raw('COUNT(DISTINCT (CASE WHEN orders.payment_status IN (' . $this->paymentStatusCancel() . ') THEN NULL ELSE orders.id END)) AS countOrders'),
                DB::raw('SUM(CASE WHEN orders.payment_status IN (' . $this->paymentStatusCancel() . ') THEN NULL ELSE (basket_items.price * basket_items.qty) END) AS amountOrders'),
                DB::raw('SUM(CASE WHEN orders.payment_status IN (' . $this->paymentStatusCancel() . ') THEN NULL ELSE basket_items.qty END) AS countProducts'),
            ])
            ->leftJoin('basket_items', 'basket_items.basket_id', '=', 'orders.basket_id')
            ->where('orders.created_at', '>=', $request->start)
            ->where('orders.created_at', '<=', $request->end)
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $results = [];

        $start = new DateTime($request->start);
        $end = new DateTime($request->end);
        for($dt = $start; $dt <= $end; $dt->modify('+1 month')){
            $month = $dt->format("Y-m-01");
            $item['month'] = $month;
            $this->recalculation($item);
            $results[$month] = $item;
        }

        foreach ($orders->toArray() as $item) {
            $month = $item['month'];
            $this->recalculation($item);
            $results[$month] = $item;
        }

        $this->aggregate($results);

        if ((new DateTime($request->start))->format('Y-m-01') === (new DateTime())->format('Y-m-01')) {
            $this->clearAggregateByKey($results, (new DateTime())->format('Y-m-01'));
        }

        return array_values($results);
    }

    private function getOrderTypeName(int $type): string
    {
        return match ($type) {
            Basket::TYPE_PRODUCT => 'Товары',
            Basket::TYPE_MASTER => 'Мастер-классы',
            Basket::TYPE_CERTIFICATE => 'Сертификаты',
            default => '',
        };
    }
    private function paymentStatusCancel(): string
    {
        return implode(',' ,[PaymentStatus::NOT_PAID, PaymentStatus::TIMEOUT, PaymentStatus::ERROR]);
    }

    private function recalculation(array &$item): void
    {
        $item['amountOrdersFull'] = isset($item['amountOrdersFull']) ? (float) $item['amountOrdersFull'] : 0;
        $item['countOrdersFull'] = isset($item['countOrdersFull']) ? (int) $item['countOrdersFull'] : 0;
        $item['countProductsFull'] = isset($item['countProductsFull']) ? (int) $item['countProductsFull'] : 0;

        $item['amountOrders'] = isset($item['amountOrders']) ? (float) $item['amountOrders'] : 0;
        $item['countOrders'] = isset($item['countOrders']) ? (int) $item['countOrders'] : 0;
        $item['countProducts'] = isset($item['countProducts']) ? (int) $item['countProducts'] : 0;

        $item['amountOrdersCancel'] = ($item['amountOrdersFull'] - $item['amountOrders']);
        $item['countOrdersCancel'] = ($item['countOrdersFull'] - $item['countOrders']);
        $item['countProductsCancel'] = ($item['countProductsFull'] - $item['countProducts']);
    }

    private function aggregate(array &$results, bool $addForecastAmount = false): void
    {
        $aggAmountOrders = 0;
        $aggAmountOrdersFull = 0;
        $aggAmountOrdersCancel = 0;
        $keyLast = null;

        foreach ($results as $key => $item) {
            $aggAmountOrders += $item['amountOrders'];
            $aggAmountOrdersFull += $item['amountOrdersFull'];
            $aggAmountOrdersCancel += $item['amountOrdersCancel'];

            $item['aggAmountOrders'] = $aggAmountOrders;
            $item['aggAmountOrdersFull'] = $aggAmountOrdersFull;
            $item['aggAmountOrdersCancel'] = $aggAmountOrdersCancel;

            $keyLast = $key;
            $results[$key] = $item;
        }

        if ($addForecastAmount && $keyLast) {
            $results[$keyLast]['forecastAmountOrders'] = $aggAmountOrders;
        }
    }

    private function clearAggregateByKey(array &$results, string $clearKey): void
    {
        $countCleared = 0;
        $countFull = count($results);
        $forecastAmountOrders = null;
        $keyLast = null;
        foreach ($results as $key => $item) {
            if ($key === $clearKey) {
                $forecastAmountOrders = $item['aggAmountOrders'];
            }

            if ($key > $clearKey) {
                $item['aggAmountOrders'] = null;
                $item['aggAmountOrdersFull'] = null;
                $item['aggAmountOrdersCancel'] = null;
                $item['amountOrders'] = null;
                $item['amountOrdersFull'] = null;
                $item['amountOrdersCancel'] = null;

                $results[$key] = $item;
                $keyLast = $key;
                ++$countCleared;
            }
        }

        if ($countCleared && $keyLast) {
            $forecastAmountOrders = round(($countFull * $forecastAmountOrders) / ($countFull - $countCleared), 2);
            $results[$keyLast]['forecastAmountOrders'] = $forecastAmountOrders;
        }
    }
}
