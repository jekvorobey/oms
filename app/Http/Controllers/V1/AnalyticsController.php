<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AnalyticsRequest;
use App\Http\Requests\AnalyticsTopRequest;
use App\Services\AnalyticsService\AnalyticsService;
use Exception;
use Illuminate\Http\JsonResponse;

class AnalyticsController extends Controller
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
    public function productsShipments(AnalyticsRequest $request, AnalyticsService $service): JsonResponse
    {
        $data = $request->validated();

        return response()->json(
            $service->getCountedByStatusProductItemsForPeriod($data['merchantId'], $data['start'], $data['end'])
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
    public function sales(AnalyticsRequest $request, AnalyticsService $service): JsonResponse
    {
        $data = $request->validated();

        return response()->json(
            $service->getMerchantSalesAnalytics($data['merchantId'], $data['start'], $data['end'])
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
    public function bestsellers(AnalyticsTopRequest $request, AnalyticsService $service): JsonResponse
    {
        $data = $request->validated();

        return response()->json(
            $service->getMerchantBestsellers($data['merchantId'], $data['start'], $data['end'], $data['limit'])
        );
    }
}
