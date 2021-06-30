<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Order\Order;
use App\Models\Order\OrderDiscount;

/**
 * Class OrderDiscountController
 * @package App\Http\Controllers\V1
 */
class OrderDiscountController extends Controller
{
    /**
     * @OA\Get(
     *     path="api/v1/orders/discounts/{discountId}/kpi",
     *     tags={"KPI For Discount"},
     *     description="",
     *     @OA\Parameter(name="discountId", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response="200",
     *         description="",
     *         @OA\JsonContent(
     *             @OA\Property(property="orders_sum_with_discount", type="integer"),
     *             @OA\Property(property="saved_sum", type="number"),
     *             @OA\Property(property="customers_count", type="integer"),
     *             @OA\Property(property="orders_count", type="integer"),
     *         )
     *     )
     * )
     * @return \Illuminate\Http\JsonResponse
     */
    public function KPIForDiscount(int $discountId)
    {
        # Сумма заказов с использованием скидки (в рублях)
        $ordersSumWithDiscount = (int) Order::query()
            ->forDiscountReport($discountId)
            ->sum('price');

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
