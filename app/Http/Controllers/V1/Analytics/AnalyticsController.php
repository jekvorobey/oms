<?php

namespace App\Http\Controllers\V1\Analytics;

use App\Http\Controllers\Controller;
use App\Http\Requests\AnalyticsRequest;
use App\Services\AnalyticsService\AnalyticsApiService;
use Exception;
use Illuminate\Http\JsonResponse;
use OpenApi\Annotations as OA;
use function response;

class AnalyticsController extends Controller
{
    /**
     * @OA\Get(
     *     path="api/v1/analytics/competition",
     *     tags={"Analytics"},
     *     description="",
     *     @OA\Response(
     *         response="200",
     *         description="",
     *         @OA\JsonContent()
     *     )
     * )
     * @throws Exception
     */
    public function competition(AnalyticsRequest $request, AnalyticsApiService $service): JsonResponse
    {
        return response()->json(
            $service->competition($request)
        );
    }

    /**
     * @OA\Get(
     *     path="api/v1/analytics/dump-orders",
     *     tags={"Analytics"},
     *     description="",
     *     @OA\Response(
     *         response="200",
     *         description="",
     *         @OA\JsonContent()
     *     )
     * )
     * @throws Exception
     */
    public function dumpOrders(AnalyticsRequest $request, AnalyticsApiService $service): JsonResponse
    {
        return response()->json(
            $service->dumpOrders($request)
        );
    }
}
