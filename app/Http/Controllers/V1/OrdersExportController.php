<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Order\OrderExport;
use Greensight\CommonMsa\Rest\Controller\CountAction;
use Greensight\CommonMsa\Rest\Controller\DeleteAction;
use Greensight\CommonMsa\Rest\Controller\ReadAction;
use Greensight\CommonMsa\Rest\Controller\UpdateAction;
use Greensight\CommonMsa\Rest\Controller\Validation\RequiredOnPost;
use Greensight\CommonMsa\Rest\RestQuery;
use Greensight\CommonMsa\Rest\RestSerializable;
use Greensight\CommonMsa\Services\RequestInitiator\RequestInitiator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class OrdersExportController
 * @package App\Http\Controllers\V1
 */
class OrdersExportController extends Controller
{
    use CountAction {
        count as countTrait;
    }

    /**
     * @OA\Get(
     *     path="api/v1/orders/{id}/exports",
     *     tags={"Заказы"},
     *     description="Получить значение груза с ID",
     *     @OA\Parameter(name="id", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="include", required=false, in="query", @OA\Schema(type="array", @OA\Items(type="string")), description="параметр json-api запроса include"),
     *     @OA\Parameter(name="fields", required=false, in="query", @OA\Schema(type="array", @OA\Items(type="string")), description="параметр json-api запроса fields"),
     *     @OA\Parameter(name="filter", required=false, in="query", @OA\Schema(type="array", @OA\Items(type="string")), description="параметр json-api запроса filter"),
     *     @OA\Parameter(name="sort", required=false, in="query", @OA\Schema(type="array", @OA\Items(type="string")), description="параметр json-api запроса sort"),
     *     @OA\Parameter(name="page", required=false, in="query", @OA\Schema(type="array", @OA\Items(type="integer")), description="параметр json-api запроса page"),
     *     @OA\Response(
     *         response="200",
     *         description="",
     *         @OA\JsonContent(
     *             @OA\Property(property="items", type="array", @OA\Items(ref="#/components/schemas/Cargo"))
     *         )
     *     )
     * )
     */
    use ReadAction {
        read as readTrait;
    }

    /**
     * @OA\Delete(
     *     path="api/v1/orders/{id}/exports/{exportId}",
     *     tags={"Заказы"},
     *     description="",
     *     @OA\Parameter(name="id", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="exportId", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\Response(response="204", description=""),
     *     @OA\Response(response="404", description="Сущность не найдена"),
     *     @OA\Response(response="500", description="Не удалось удалить сущность"),
     * )
     */
    use DeleteAction;

    /**
     * @OA\Put(
     *     path="api/v1/orders/{id}/exports/{exportId}",
     *     tags={"Заказы"},
     *     description="Изменить значения груза.",
     *     @OA\Parameter(name="id", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *      required=true,
     *      description="Изменить значение для public event types.",
     *          @OA\JsonContent(ref="#/components/schemas/Cargo")
     *     ),
     *     @OA\Response(response="204", description="Данные сохранены"),
     *     @OA\Response(response="404", description="product not found"),
     * )
     */
    use UpdateAction;

    public function modelClass(): string
    {
        return OrderExport::class;
    }

    /**
     * @inheritDoc
     */
    protected function writableFieldList(): array
    {
        return OrderExport::FILLABLE;
    }

    /**
     * @inheritDoc
     */
    protected function inputValidators(): array
    {
        return [
            'order_id' => [new RequiredOnPost(), 'int'],
            'merchant_integration_id' => [new RequiredOnPost(), 'int'],
            'order_xml_id' => [new RequiredOnPost(), 'string'],
        ];
    }

    /**
     * @OA\Get(
     *     path="api/v1/orders/{id}/exports/{exportId}",
     *     tags={"Заказы"},
     *     description="",
     *     @OA\Parameter(name="id", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="exportId", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *      required=true,
     *      description="",
     *      @OA\JsonContent(
     *          required={"name"},
     *          @OA\Property(property="id", type="integer"),
     *          @OA\Property(property="exportId", type="integer"),
     *      ),
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="",
     *         @OA\JsonContent(
     *             @OA\Property(property="total", type="integer"),
     *             @OA\Property(property="pages", type="integer"),
     *             @OA\Property(property="pageSize", type="integer"),
     *         )
     *     )
     * )
     * @return \Illuminate\Http\JsonResponse
     */
    public function read(Request $request, RequestInitiator $client)
    {
        /** @var Model|RestSerializable $modelClass */
        $modelClass = $this->modelClass();

        $restQuery = new RestQuery($request);
        $orderId = $request->route('id');
        $exportId = $request->route('exportId');
        if ($orderId) {
            if ($exportId) {
                $query = $modelClass::modifyQuery(
                    $modelClass::query()
                    ->where('order_id', $orderId)
                    ->where('id', $exportId),
                    $restQuery
                );

                /** @var RestSerializable $model */
                $model = $query->first();
                if (!$model) {
                    throw new NotFoundHttpException();
                }

                $items = [
                    $model->toRest($restQuery),
                ];
            } else {
                $pagination = $restQuery->getPage();
                $baseQuery = $modelClass::query();
                if ($pagination) {
                    $baseQuery->offset($pagination['offset'])->limit($pagination['limit']);
                }
                $query = $modelClass::modifyQuery($baseQuery->where('order_id', $orderId), $restQuery);

                $items = $query->get()
                    ->map(function (RestSerializable $model) use ($restQuery) {
                        return $model->toRest($restQuery);
                    });
            }

            return response()->json([
                'items' => $items,
            ]);
        } else {
            return $this->readTrait($request, $client);
        }
    }

    /**
     * @OA\Get(
     *     path="api/v1/orders/exports/count",
     *     tags={"Заказы"},
     *     description="Количество сущностей Заказы на экспорт",
     *     @OA\Response(
     *         response="200",
     *         description="",
     *         @OA\JsonContent(
     *             @OA\Property(property="total", type="integer"),
     *             @OA\Property(property="pages", type="integer"),
     *             @OA\Property(property="pageSize", type="integer"),
     *         )
     *     )
     * )
     * @return \Illuminate\Http\JsonResponse
     */
    public function count(Request $request, RequestInitiator $client)
    {
        $orderId = $request->route('id');
        if ($orderId) {
            /** @var Model|RestSerializable $modelClass */
            $modelClass = $this->modelClass();
            $restQuery = new RestQuery($request);

            $pagination = $restQuery->getPage();
            $pageSize = $pagination ? $pagination['limit'] : ReadAction::$PAGE_SIZE;

            $query = $modelClass::modifyQuery($modelClass::query()->where('order_id', $orderId), $restQuery);
            $total = $query->count();

            $pages = ceil($total / $pageSize);

            return response()->json([
                'total' => $total,
                'pages' => $pages,
                'pageSize' => $pageSize,
            ]);
        } else {
            return $this->countTrait($request, $client);
        }
    }

    /**
     * @OA\Post (
     *     path="api/v1/orders/{id}/exports",
     *     tags={"Заказы"},
     *     description="",
     *     @OA\Parameter(name="id", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="include", required=false, in="query", @OA\Schema(type="array", @OA\Items(type="string")), description="параметр json-api запроса include"),
     *     @OA\Parameter(name="fields", required=false, in="query", @OA\Schema(type="array", @OA\Items(type="string")), description="параметр json-api запроса fields"),
     *     @OA\Parameter(name="filter", required=false, in="query", @OA\Schema(type="array", @OA\Items(type="string")), description="параметр json-api запроса filter"),
     *     @OA\Parameter(name="sort", required=false, in="query", @OA\Schema(type="array", @OA\Items(type="string")), description="параметр json-api запроса sort"),
     *     @OA\Parameter(name="page", required=false, in="query", @OA\Schema(type="array", @OA\Items(type="integer")), description="параметр json-api запроса page"),
     *     @OA\Response(
     *         response="201",
     *         description="",
     *          @OA\JsonContent(
     *              @OA\Property(property="id", type="integer"),
     *          ),
     *
     *     ),
     *     @OA\Response(response="400", description="Bad request"),
     *     @OA\Response(response="500", description="unable to save delivery"),
     * )
     */
    public function create(int $orderId, Request $request): JsonResponse
    {
        /** @var Model $modelClass */
        $modelClass = $this->modelClass();
        $data = $request->only($this->writableFieldList());
        $data['order_id'] = $orderId;
        if ($this->isInvalid($data)) {
            throw new BadRequestHttpException($this->validationErrors->first());
        }
        /** @var OrderExport $model */
        $model = new $modelClass($data);
        $ok = $model->save();

        if (!$ok) {
            throw new HttpException(500);
        }

        return response()->json([
            'id' => $model->id,
        ], 201);
    }
}
