<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Order\OrderHistoryEvent;
use Greensight\CommonMsa\Rest\RestQuery;
use Illuminate\Http\Request;


/**
 * Class OrdersController
 * @package App\Http\Controllers\V1
 */
class OrdersHistoryController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/orders/history",
     *     tags={"order-history"},
     *     summary="Получить список событий измененя заказов",
     *     operationId="listOrderHistory",
     *     @OA\Response(
     *         response=200,
     *         description="OK",
     *         @OA\JsonContent(
     *             @OA\Property(property="items",type="array",
     *                @OA\Items(
     *                     @OA\Property(property="id",type="integer")
     *                )
     *             )
     *         )
     *     ),
     * )
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function read(Request $request)
    {
        $restQuery = new RestQuery($request);
        return response()->json([
            'items' => OrderHistoryEvent::findByRest($restQuery)->get(),
        ]);
    }
    /**
     * @OA\Get(
     *     path="/api/v1/orders/history/count",
     *     tags={"order-history"},
     *     summary="Получить количество событий измененя заказов",
     *     operationId="countOrderHistory",
     *     @OA\Response(
     *         response=200,
     *         description="OK",
     *         @OA\JsonContent(ref="#/components/schemas/CountResult")
     *     ),
     * )
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function count(Request $request)
    {
        $restQuery = new RestQuery($request);
        $pagination = $restQuery->getPage();
        $pageSize = $pagination ? $pagination['limit'] : 10;
    
        $query = OrderHistoryEvent::findByRest($restQuery);
        $total = $query->count();
        $pages = ceil($total / $pageSize);
        
        return response()->json([
            'total' => $total,
            'pages' => $pages,
            'pageSize' => $pageSize,
        ]);
    }
}
