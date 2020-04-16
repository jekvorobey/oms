<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Order\Order;
use App\Models\Order\OrderDiscount;
use App\Models\Order\OrderPromoCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Class OrderDiscountController
 * @package App\Http\Controllers\V1
 */
class OrderDiscountController extends Controller
{
    /**
     * @param int $discountId
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function KPIForDiscount(int $discountId)
    {
        # Сумма заказов с использованием скидки (в рублях)
        $ordersSumWithDiscount = (int) Order::query()
            ->forDiscountReport($discountId)
            ->sum(DB::raw('`price` + `delivery_price`'));

        # Количество пользователей, которые воспользовались скидкой
        $customersCount = Order::query()
            ->select('customer_id')
            ->forDiscountReport($discountId)
            ->distinct()
            ->count('customer_id');


        # Сумма, которую сэкономили покупатели (в рублях)
        $savedSum = (int) OrderDiscount::query()->forDiscountReport($discountId)->sum('change');

        # Количество заказов со скидкой $discountId
        $ordersCount = OrderDiscount::query()
            ->select('order_id')
            ->forDiscountReport($discountId)
            ->distinct()
            ->count();

        return response()->json([
            'orders_sum_with_discount' => $ordersSumWithDiscount,
            'saved_sum' => $savedSum,
            'customers_count' => $customersCount,
            'orders_count' => $ordersCount,
        ]);
    }
}
