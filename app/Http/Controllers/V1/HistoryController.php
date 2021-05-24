<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Delivery\Cargo;
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
     * Получить список событий изменения заказов
     */
    public function readByOrder(int $orderId, Request $request): JsonResponse
    {
        return $this->readByMainEntity(Order::class, $orderId, $request);
    }

    /**
     * Получить список событий изменения отправлений
     */
    public function readByShipment(int $shipmentId, Request $request): JsonResponse
    {
        return $this->readByMainEntity(Shipment::class, $shipmentId, $request);
    }

    /**
     * Получить список событий изменения груза
     */
    public function readByCargo(int $cargoId, Request $request): JsonResponse
    {
        return $this->readByMainEntity(Cargo::class, $cargoId, $request);
    }

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
                $query->where('main_entity_type', end($mainEntityClass))
                    ->where('main_entity_id', $mainEntityId);
            }),
            $restQuery
        );

        $items = $query->get()
            ->map(function (RestSerializable $model) use ($restQuery) {
                return $model->toRest($restQuery);
            });

        return response()->json([
            'items' => $items,
        ]);
    }

    /**
     * Получить количество событий изменения заказов
     */
    public function countByOrder(int $orderId, Request $request): JsonResponse
    {
        return $this->countByMainEntity(Order::class, $orderId, $request);
    }

    /**
     * Получить количество событий изменения отправления
     */
    public function countByShipment(int $shipmentId, Request $request): JsonResponse
    {
        return $this->countByMainEntity(Shipment::class, $shipmentId, $request);
    }

    /**
     * Получить количество событий изменения груза
     */
    public function countByCargo(int $cargoId, Request $request): JsonResponse
    {
        return $this->countByMainEntity(Cargo::class, $cargoId, $request);
    }

    protected function countByMainEntity(string $mainEntity, int $mainEntityId, Request $request): JsonResponse
    {
        $restQuery = new RestQuery($request);

        $pagination = $restQuery->getPage();
        $pageSize = $pagination ? $pagination['limit'] : ReadAction::$PAGE_SIZE;
        $baseQuery = History::query();

        $mainEntityClass = explode('\\', $mainEntity);
        $query = History::modifyQuery(
            $baseQuery->whereHas('historyMainEntities', function (Builder $query) use ($mainEntityClass, $mainEntityId) {
                $query->where('main_entity_type', end($mainEntityClass))
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
