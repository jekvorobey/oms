<?php

namespace App\Http\Controllers\V1\Analytics;

use App\Http\Controllers\Controller;
use App\Http\Requests\DashboardsAnalyticsRequest;
use App\Services\AnalyticsService\DashboardsAnalyticsService;
use Exception;
use Illuminate\Http\JsonResponse;
use OpenApi\Annotations as OA;
use function response;

class DashboardsAnalyticsController extends Controller
{
    /**
     * @OA\Get(
     *     path="api/v1/analytics/dashboard/sales/all-period-by-day",
     *     tags={"Dashboard"},
     *     description="",
     *     @OA\Response(
     *         response="200",
     *         description="",
     *         @OA\JsonContent()
     *     )
     * )
     * @throws Exception
     */
    public function salesAllPeriodByDay(DashboardsAnalyticsService $service): JsonResponse
    {
        return response()->json(
            $service->salesAllPeriodByDay()
        );
    }

    /**
     * @OA\Get(
     *     path="api/v1/analytics/dashboard/sales/day-by-hour",
     *     tags={"Dashboard"},
     *     description="",
     *     @OA\Response(
     *         response="200",
     *         description="",
     *         @OA\JsonContent()
     *     )
     * )
     * @throws Exception
     */
    public function salesDayByHour(DashboardsAnalyticsRequest $request, DashboardsAnalyticsService $service): JsonResponse
    {
        return response()->json(
            $service->salesDayByHour($request)
        );
    }

    /**
     * @OA\Get(
     *     path="api/v1/analytics/dashboard/sales/month-by-day",
     *     tags={"Dashboard"},
     *     description="",
     *     @OA\Response(
     *         response="200",
     *         description="",
     *         @OA\JsonContent()
     *     )
     * )
     * @throws Exception
     */
    public function salesMonthByDay(DashboardsAnalyticsRequest $request, DashboardsAnalyticsService $service): JsonResponse
    {
        return response()->json(
            $service->salesMonthByDay($request)
        );
    }

    /**
     * @OA\Get(
     *     path="api/v1/analytics/dashboard/sales/year-by-month",
     *     tags={"Dashboard"},
     *     description="",
     *     @OA\Response(
     *         response="200",
     *         description="",
     *         @OA\JsonContent()
     *     )
     * )
     * @throws Exception
     */
    public function salesYearByMonth(DashboardsAnalyticsRequest $request, DashboardsAnalyticsService $service): JsonResponse
    {
        return response()->json(
            $service->salesYearByMonth($request)
        );
    }
}
