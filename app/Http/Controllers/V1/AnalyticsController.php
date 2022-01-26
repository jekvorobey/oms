<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService\AnalyticsService;
use Exception;
use Illuminate\Http\JsonResponse;

class AnalyticsController extends Controller
{
    /**
     * @OA\Get(
     *     path="api/v1/merchant_analytics/{merchantId}/products_shipments/{start}/{end}",
     *     tags={"Поставки"},
     *     description="Получить количества отправлений, товаров и суммы по товарам мерчанта за период, сгруппированные по статусу",
     *     @OA\Parameter(name="merchantId", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="year", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="month", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response="200",
     *         description="",
     *         @OA\JsonContent()
     *     )
     * )
     * Получить количества отправлений, товаров и суммы по товарам мерчанта за период, сгруппированные по статусу
     * @throws Exception
     */
    public function productsShipments(
        int $merchantId,
        string $start,
        string $end,
        AnalyticsService $service
    ): JsonResponse {
        $currentPeriod = $service->getCountedByStatusProductItemsForPeriod($merchantId, $start, $end);
        return response()->json($currentPeriod);
    }

    /**
     * @OA\Get(
     *     path="api/v1/merchant_analytics/{merchantId}/sales/{start}/{end}",
     *     tags={"Поставки"},
     *     description="Получить продажи мерчанта в конкретный период интервально",
     *     @OA\Parameter(name="merchantId", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="year", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="month", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response="200",
     *         description="",
     *         @OA\JsonContent()
     *     )
     * )
     * Получить продажи мерчанта в конкретный период интервально.
     * @throws Exception
     */
    public function sales(
        int $merchantId,
        string $start,
        string $end,
        string $intervalType,
        AnalyticsService $service
    ): JsonResponse {
        return response()->json($service->getMerchantSalesAnalytics($merchantId, $start, $end, $intervalType));
    }

    /**
     * @OA\Get(
     *     path="api/v1/merchant_analytics/{merchantId}/top/products/{start}/{end}",
     *     tags={"Поставки"},
     *     description="Получить список бестселлеров мерчанта",
     *     @OA\Parameter(name="merchantId", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="year", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="month", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response="200",
     *         description="",
     *         @OA\JsonContent()
     *     )
     * )
     * Получить список бестселлеров мерчанта.
     * @throws Exception
     */
    public function bestsellers(int $merchantId, string $start, string $end, AnalyticsService $service): JsonResponse
    {
        return response()->json($service->getMerchantTopProducts($merchantId, $start, $end));
    }
}
