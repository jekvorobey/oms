<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Delivery\Shipment;
use App\Models\History\History;
use App\Models\Order\Order;
use Greensight\CommonMsa\Rest\Controller\ReadAction;
use Greensight\CommonMsa\Rest\RestQuery;
use Greensight\CommonMsa\Rest\RestSerializable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


/**
 * Class HistoryController
 * @package App\Http\Controllers\V1
 */
class HistoryController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/orders/{$id}/history",
     *     tags={"order-history"},
     *     summary="Получить список событий изменения заказов",
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
    public function readByOrder(int $orderId, Request $request): JsonResponse
    {
        return $this->readByMainEntity(Order::class, $orderId, $request);
    }
    
    /**
     * @OA\Get(
     *     path="/api/v1/shipments/{$id}/history",
     *     tags={"shipment-history"},
     *     summary="Получить список событий изменения отправлений",
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
    public function readByShipment(int $shipmentId, Request $request): JsonResponse
    {
        return $this->readByMainEntity(Shipment::class, $shipmentId, $request);
    }
    
    /**
     * @param  string  $mainEntity
     * @param  int  $mainEntityId
     * @param  Request  $request
     * @return JsonResponse
     */
    protected function readByMainEntity(string $mainEntity, int $mainEntityId, Request $request): JsonResponse
    {
        $restQuery = new RestQuery($request);
    
        $pagination = $restQuery->getPage();
        $baseQuery = History::query();
        if ($pagination) {
            $baseQuery->offset($pagination['offset'])->limit($pagination['limit']);
        }
        $mainEntityClass = explode('\\', $mainEntity);
        $query = History::modifyQuery(
            $baseQuery->whereHas('historyMainEntities', function (Builder $query) use ($mainEntityClass, $mainEntityId) {
                $query->where('main_entity', end($mainEntityClass))
                    ->where('main_entity_id', $mainEntityId);
            }),
            $restQuery
        );
    
        $items = $query->get()
            ->map(function (RestSerializable $model) use ($restQuery) {
                return $model->toRest($restQuery);
            });
    
        return response()->json([
            'items' => $items
        ]);
    }
    
    /**
     * @OA\Get(
     *     path="/api/v1/orders/{id}/history/count",
     *     tags={"order-history"},
     *     summary="Получить количество событий изменения заказов",
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
    public function countByOrder(int $orderId, Request $request): JsonResponse
    {
        return $this->countByMainEntity(Order::class, $orderId, $request);
    }
    
    /**
     * @OA\Get(
     *     path="/api/v1/shipments/{id}/history/count",
     *     tags={"shipment-history"},
     *     summary="Получить количество событий изменения отправления",
     *     operationId="countShipmentHistory",
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
    public function countByShipment(int $shipmentId, Request $request): JsonResponse
    {
        return $this->countByMainEntity(Shipment::class, $shipmentId, $request);
    }
    
    /**
     * @param  string  $mainEntity
     * @param  int  $mainEntityId
     * @param  Request  $request
     * @return JsonResponse
     */
    protected function countByMainEntity(string $mainEntity, int $mainEntityId, Request $request): JsonResponse
    {
        $restQuery = new RestQuery($request);
    
        $pagination = $restQuery->getPage();
        $pageSize = $pagination ? $pagination['limit'] : ReadAction::$PAGE_SIZE;
        $baseQuery = History::query();
    
        $mainEntityClass = explode('\\', $mainEntity);
        $query = History::modifyQuery(
            $baseQuery->whereHas('historyMainEntities', function (Builder $query) use ($mainEntityClass, $mainEntityId) {
                $query->where('main_entity', end($mainEntityClass))
                    ->where('main_entity_id', $mainEntityId);
            }),
            $restQuery
        );
        $total = $query->count();
    
        $pages = ceil($total / $pageSize);
    
        return response()->json([
            'total' => $total,
            'pages' => $pages,
            'pageSize' => $pageSize,
        ]);
    }
}
