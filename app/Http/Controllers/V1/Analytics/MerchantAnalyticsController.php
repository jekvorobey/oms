<?php

namespace App\Http\Controllers\V1\Analytics;

use App\Http\Controllers\Controller;
use App\Http\Requests\MerchantAnalyticsRequest;
use App\Http\Requests\MerchantAnalyticsTopRequest;
use App\Services\AnalyticsService\MerchantAnalyticsService;
use Exception;
use Illuminate\Http\JsonResponse;
use function response;

class MerchantAnalyticsController extends Controller
{
    /**
     * @OA\Get(
     *     path="api/v1/merchant_analytics/products_shipments",
     *     tags={"Поставки"},
     *     description="Получить количества отправлений, товаров и суммы по товарам мерчанта за период, сгруппированные по статусу",
     *     @OA\Response(
     *         response="200",
     *         description="",
     *         @OA\JsonContent()
     *     )
     * )
     * Получить количества отправлений, товаров и суммы по товарам мерчанта за период, сгруппированные по статусу
     * @throws Exception
     */
    public function productsShipments(MerchantAnalyticsRequest $request, MerchantAnalyticsService $service): JsonResponse
    {
        return response()->json(
            $service->getCountedByStatusProductItemsForPeriod($request)
        );
    }

    /**
     * @OA\Get(
     *     path="api/v1/merchant_analytics/sales",
     *     tags={"Поставки"},
     *     description="Получить продажи мерчанта в конкретный период интервально",
     *     @OA\Response(
     *         response="200",
     *         description="",
     *         @OA\JsonContent()
     *     )
     * )
     * Получить продажи мерчанта в конкретный период интервально.
     * @throws Exception
     */
    public function sales(MerchantAnalyticsRequest $request, MerchantAnalyticsService $service): JsonResponse
    {
        return response()->json(
            $service->getMerchantSalesAnalytics($request)
        );
    }

    /**
     * @OA\Get(
     *     path="api/v1/merchant_analytics/bestsellers",
     *     tags={"Поставки"},
     *     description="Получить список бестселлеров мерчанта",
     *     @OA\Response(
     *         response="200",
     *         description="",
     *         @OA\JsonContent()
     *     )
     * )
     * Получить список бестселлеров мерчанта.
     * @throws Exception
     */
    public function bestsellers(MerchantAnalyticsTopRequest $request, MerchantAnalyticsService $service): JsonResponse
    {
        return response()->json(
            $service->getMerchantBestsellers($request)
        );
    }

    /**
     * @OA\Get(
     *     path="api/v1/merchant_analytics/fastest",
     *     tags={"Поставки"},
     *     description="Получить топ продуктов мерчанта по скорости продаж",
     *     @OA\Response(
     *         response="200",
     *         description="",
     *         @OA\JsonContent()
     *     )
     * )
     * Получить топ продуктов мерчанта по скорости продаж.
     * @throws Exception
     */
    public function fastest(MerchantAnalyticsTopRequest $request, MerchantAnalyticsService $service): JsonResponse
    {
        return response()->json(
            $service->getProductsTurnover($request)
        );
    }

    /**
     * @OA\Get(
     *     path="api/v1/merchant_analytics/outsiders",
     *     tags={"Поставки"},
     *     description="Получить топ продуктов-аутсайдеров мерчанта",
     *     @OA\Response(
     *         response="200",
     *         description="",
     *         @OA\JsonContent()
     *     )
     * )
     * Получить топ продуктов-аутсайдеров мерчанта.
     * @throws Exception
     */
    public function outsiders(MerchantAnalyticsTopRequest $request, MerchantAnalyticsService $service): JsonResponse
    {
        return response()->json(
            $service->getProductsTurnover($request, true)
        );
    }
}
