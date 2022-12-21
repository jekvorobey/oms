<?php

namespace App\Http\Controllers\V1\Analytics;

use App\Http\Controllers\Controller;
use App\Http\Requests\AnalyticsRequest;
use App\Services\AnalyticsService\AnalyticsDumpOrdersService;
use Exception;
use Illuminate\Http\JsonResponse;
use OpenApi\Annotations as OA;
use function response;

class AnalyticsController extends Controller
{
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
    public function dumpOrders(AnalyticsRequest $request, AnalyticsDumpOrdersService $service): JsonResponse
    {
        return response()->json(
            $service->dumpOrders($request)
        );
    }
}
