<?php

namespace App\Http\Controllers\V1\Delivery;

use App\Http\Controllers\Controller;
use App\Models\Delivery\Cargo;
use App\Models\Delivery\CargoStatus;
use App\Models\Delivery\Shipment;
use App\Models\Delivery\ShipmentExport;
use App\Models\Delivery\ShipmentItem;
use App\Models\Delivery\ShipmentStatus;
use App\Models\Payment\PaymentStatus;
use App\Services\CargoService;
use App\Services\DeliveryService;
use App\Services\ShipmentService;
use Exception;
use Greensight\CommonMsa\Dto\FileDto;
use Greensight\CommonMsa\Rest\Controller\CountAction;
use Greensight\CommonMsa\Rest\Controller\DeleteAction;
use Greensight\CommonMsa\Rest\Controller\ReadAction;
use Greensight\CommonMsa\Rest\Controller\UpdateAction;
use Greensight\CommonMsa\Rest\Controller\Validation\RequiredOnPost;
use Greensight\CommonMsa\Rest\RestQuery;
use Greensight\CommonMsa\Rest\RestSerializable;
use Greensight\CommonMsa\Services\FileService\FileService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class ShipmentsController
 * @package App\Http\Controllers\V1\Delivery
 */
class ShipmentsController extends Controller
{
    /**
     * @OA\Get(
     *     path="api/v1/shipments/count",
     *     tags={"Поставки"},
     *     description="Количество сущностей вариантов значений поставки",
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
     */
    use CountAction;
    /**
     * @OA\Get(
     *     path="api/v1/shipments",
     *     tags={"Поставки"},
     *     description="Получить список поставок",
     *     @OA\Parameter(name="include", required=false, in="query", @OA\Schema(type="array", @OA\Items(type="string")), description="параметр json-api запроса include"),
     *     @OA\Parameter(name="fields", required=false, in="query", @OA\Schema(type="array", @OA\Items(type="string")), description="параметр json-api запроса fields"),
     *     @OA\Parameter(name="filter", required=false, in="query", @OA\Schema(type="array", @OA\Items(type="string")), description="параметр json-api запроса filter"),
     *     @OA\Parameter(name="sort", required=false, in="query", @OA\Schema(type="array", @OA\Items(type="string")), description="параметр json-api запроса sort"),
     *     @OA\Parameter(name="page", required=false, in="query", @OA\Schema(type="array", @OA\Items(type="integer")), description="параметр json-api запроса page"),
     *     @OA\Response(
     *         response="200",
     *         description="",
     *         @OA\JsonContent(
     *             @OA\Property(property="items", type="array", @OA\Items(ref="#/components/schemas/Shipment"))
     *         )
     *     )
     * )
     *
     * @OA\Get(
     *     path="api/v1/shipments/{id}",
     *     tags={"Поставки"},
     *     description="Получить значение поставки с ID",
     *     @OA\Parameter(name="id", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="include", required=false, in="query", @OA\Schema(type="array", @OA\Items(type="string")), description="параметр json-api запроса include"),
     *     @OA\Parameter(name="fields", required=false, in="query", @OA\Schema(type="array", @OA\Items(type="string")), description="параметр json-api запроса fields"),
     *     @OA\Parameter(name="filter", required=false, in="query", @OA\Schema(type="array", @OA\Items(type="string")), description="параметр json-api запроса filter"),
     *     @OA\Parameter(name="sort", required=false, in="query", @OA\Schema(type="array", @OA\Items(type="string")), description="параметр json-api запроса sort"),
     *     @OA\Parameter(name="page", required=false, in="query", @OA\Schema(type="array", @OA\Items(type="integer")), description="параметр json-api запроса page"),
     *     @OA\Response(
     *         response="200",
     *         description="",
     *         @OA\JsonContent(ref="#/components/schemas/Shipment")
     *     )
     * )
     */
    use ReadAction;
    /**
     * @OA\Put(
     *     path="api/v1/shipments/{id}",
     *     tags={"Поставки"},
     *     description="Изменить значения поставки.",
     *     @OA\Parameter(name="id", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *      required=true,
     *      description="Изменить значение поставки.",
     *          @OA\JsonContent(ref="#/components/schemas/Shipment")
     *     ),
     *     @OA\Response(response="204", description="Данные сохранены"),
     *     @OA\Response(response="404", description="product not found"),
     * )
     */
    use UpdateAction;
    /**
     * @OA\Delete(
     *     path="api/v1/shipments/{id}",
     *     tags={"Поставки"},
     *     description="Удалить поставку",
     *     @OA\Parameter(name="id", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\Response(response="204", description=""),
     *     @OA\Response(response="404", description="Сущность не найдена"),
     *     @OA\Response(response="500", description="Не удалось удалить сущность"),
     * )
     */
    use DeleteAction;

    public function modelClass(): string
    {
        return Shipment::class;
    }

    public function modelItemsClass(): string
    {
        return ShipmentItem::class;
    }

    /**
     * @inheritDoc
     */
    protected function writableFieldList(): array
    {
        return Shipment::FILLABLE;
    }

    /**
     * @inheritDoc
     */
    protected function inputValidators(): array
    {
        return [
            'delivery_id' => [new RequiredOnPost(), 'integer'],
            'merchant_id' => [new RequiredOnPost(), 'integer'],
            'store_id' => [new RequiredOnPost(), 'integer'],
            'cargo_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(ShipmentStatus::validValues())],
            'payment_status_at' => ['nullable', 'date'],
            'number' => [new RequiredOnPost(), 'string'],
            'required_shipping_at' => [new RequiredOnPost(), 'date'],
        ];
    }

    /**
     * @OA\Get(
     *     path="api/v1/shipments/active",
     *     tags={"Поставки"},
     *     description="Получить ID и сумму (руб.) принятых мерчантом заказов за период",
     *     @OA\RequestBody(
     *      required=true,
     *      description="",
     *      @OA\JsonContent(
     *          required={"merchant_id","period"},
     *          @OA\Property(property="merchant_id", type="integer"),
     *          @OA\Property(property="period", type="string"),
     *      ),
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="",
     *         @OA\JsonContent(
     *          @OA\Property(property="count", type="integer"),
     *          @OA\Property(property="period", type="string")
     *         )
     *     )
     * )
     *
     * Получить ID и сумму (руб.) принятых мерчантом заказов за период
     * @return JsonResponse
     */
    public function getActiveIds()
    {
        $data = $this->validate(request(), [
            'merchant_id' => 'required|integer',
            'period' => 'required|string',
        ]);
        $orders = Shipment::query()
            ->select(['merchant_id', 'cost'])
            ->where([
                ['created_at', '>', $data['period']],
                ['merchant_id', '=', $data['merchant_id']],
                ['status', '>=', ShipmentStatus::AWAITING_CONFIRMATION],
                ['status', '<=', ShipmentStatus::DONE],
                ['is_canceled', '=', 0],
            ]);
        if (!$orders) {
            $orders_count = 0;
            $orders_price = 0;
        } else {
            $orders_count = $orders->count('merchant_id');
            $orders_price = $orders->sum('cost');
        }

        return response()->json([
            'count' => $orders_count,
            'price' => $orders_price,
        ], 200);
    }

    /**
     * @OA\Get(
     *     path="api/v1/shipments/delivered",
     *     tags={"Поставки"},
     *     description=" Получить ID и сумму (руб.) доставленных заказов у мерчанта за период",
     *     @OA\RequestBody(
     *      required=true,
     *      description="",
     *      @OA\JsonContent(
     *          required={"merchant_id","period"},
     *          @OA\Property(property="merchant_id", type="integer"),
     *          @OA\Property(property="period", type="string"),
     *      ),
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="",
     *         @OA\JsonContent(
     *          @OA\Property(property="count", type="integer"),
     *          @OA\Property(property="period", type="string")
     *         )
     *     )
     * )
     * Получить ID и сумму (руб.) доставленных заказов у мерчанта за период
     * @return JsonResponse
     */
    public function getDeliveredIds()
    {
        $data = $this->validate(request(), [
            'merchant_id' => 'required|integer',
            'period' => 'required|string',
        ]);
        $orders = Shipment::query()
            ->select(['merchant_id', 'cost'])
            ->where([
                ['created_at', '>', $data['period']],
                ['merchant_id', '=', $data['merchant_id']],
                ['status', '=', ShipmentStatus::DONE],
            ]);
        if (!$orders) {
            $orders_count = 0;
            $orders_price = 0;
        } else {
            $orders_count = $orders->count('merchant_id');
            $orders_price = $orders->sum('cost');
        }

        return response()->json([
            'count' => $orders_count,
            'price' => $orders_price,
        ], 200);
    }

    /**
     * @OA\Get(
     *     path="api/v1/deliveries/{id}/shipments/count",
     *     tags={"Поставки"},
     *     description=" Подсчитать кол-во отправлений доставки",
     *     @OA\Parameter(name="id", required=true, in="path", @OA\Schema(type="integer")),
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
     * Подсчитать кол-во отправлений доставки
     */
    public function countByDelivery(int $deliveryId, Request $request): JsonResponse
    {
        /** @var Model|RestSerializable $modelClass */
        $modelClass = $this->modelClass();
        $restQuery = new RestQuery($request);

        $pagination = $restQuery->getPage();
        $pageSize = $pagination ? $pagination['limit'] : ReadAction::$PAGE_SIZE;
        $baseQuery = $modelClass::query();

        $query = $modelClass::modifyQuery($baseQuery->where('delivery_id', $deliveryId), $restQuery);
        $total = $query->count();

        $pages = ceil($total / $pageSize);

        return response()->json([
            'total' => $total,
            'pages' => $pages,
            'pageSize' => $pageSize,
        ]);
    }

    /**
     * @OA\Post (
     *     path="api/v1/deliveries/{id}/shipments",
     *     tags={"Корзина"},
     *     description="Создать отправление",
     *     @OA\Parameter(name="id", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *      required=true,
     *      description="",
     *      @OA\JsonContent(
     *          @OA\Property(property="delivery_id", type="integer"),
     *          @OA\Property(property="merchant_id", type="integer"),
     *          @OA\Property(property="store_id", type="integer"),
     *          @OA\Property(property="cargo_id", type="integer"),
     *          @OA\Property(property="status", type="integer"),
     *          @OA\Property(property="payment_status_at", type="string"),
     *          @OA\Property(property="number", type="string"),
     *          @OA\Property(property="required_shipping_at", type="string"),
     *      ),
     *     ),
     *     @OA\Response(
     *         response="201",
     *         description="",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer")
     *         )
     *     ),
     *     @OA\Response(response="404", description="delivery not found"),
     *     @OA\Response(response="500", description="unable to save shipment item"),
     * )
     *
     * Создать отправление
     */
    public function create(int $deliveryId, Request $request, DeliveryService $deliveryService): JsonResponse
    {
        $deliveryService->getDelivery($deliveryId);

        $data = $request->all();
        $validator = Validator::make($data, $this->inputValidators());
        if ($validator->fails()) {
            throw new BadRequestHttpException($validator->errors()->first());
        }
        $data['delivery_id'] = $deliveryId;

        $shipment = new Shipment($data);
        $ok = $shipment->save();
        if (!$ok) {
            throw new HttpException(500, 'unable to save shipment');
        }

        return response()->json([
            'id' => $shipment->id,
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="api/v1/shipments/similar-unshipped-shipments",
     *     tags={"Поставки"},
     *     description="Получить собранные неотгруженные отправления со схожими параметрами для текущего груза (склад, служба доставки)",
     *     @OA\RequestBody(
     *      required=true,
     *      description="",
     *      @OA\JsonContent(
     *          required={"cargo_id"},
     *          @OA\Property(property="cargo_id", type="integer"),
     *      ),
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="",
     *         @OA\JsonContent(
     *             @OA\Property(property="items", type="array", @OA\Items(ref="#/components/schemas/Shipment"))
     *         )
     *     )
     * )
     * Получить собранные неотгруженные отправления со схожими параметрами для текущего груза (склад, служба доставки)
     */
    public function similarUnshippedShipments(Request $request, CargoService $cargoService): JsonResponse
    {
        $validatedData = $this->validate($request, [
            'cargo_id' => 'integer|required',
        ]);

        $cargo = $cargoService->getCargo($validatedData['cargo_id']);
        $similarCargosIds = Cargo::query()
            ->select('id')
            ->where('id', '!=', $cargo->id)
            ->where('merchant_id', $cargo->merchant_id)
            ->where('store_id', $cargo->store_id)
            ->whereIn('status', [CargoStatus::CREATED])
            ->where('delivery_service', $cargo->delivery_service)
            ->pluck('id')
            ->all();

        $shipments = Shipment::query()
            ->where('merchant_id', $cargo->merchant_id)
            ->where('store_id', $cargo->store_id)
            ->where(function (Builder $q) use ($similarCargosIds) {
                $q->whereNull('cargo_id')
                    ->orWhereIn('cargo_id', $similarCargosIds);
            })
            ->where('status', ShipmentStatus::ASSEMBLED)
            ->whereHas('delivery', function (Builder $q) use ($cargo) {
                $q->where('delivery_service', $cargo->delivery_service);
            })
            ->get();

        return response()->json([
            'items' => $shipments,
        ]);
    }

    /**
     * @OA\Get(
     *     path="api/v1/deliveries/{id}/shipments",
     *     tags={"Поставки"},
     *     description="Список отправлений доставки",
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
     *             @OA\Property(property="items", type="array", @OA\Items(ref="#/components/schemas/Shipment"))
     *         )
     *     )
     * )
     *
     * Список отправлений доставки
     */
    public function readByDelivery(int $deliveryId, Request $request): JsonResponse
    {
        /** @var Model|RestSerializable $modelClass */
        $modelClass = $this->modelClass();
        $restQuery = new RestQuery($request);

        $pagination = $restQuery->getPage();
        $baseQuery = $modelClass::query();
        if ($pagination) {
            $baseQuery->offset($pagination['offset'])->limit($pagination['limit']);
        }
        $query = $modelClass::modifyQuery($baseQuery->where('delivery_id', $deliveryId), $restQuery);

        $items = $query->get()
            ->map(function (RestSerializable $model) use ($restQuery) {
                return $model->toRest($restQuery);
            });

        return response()->json([
            'items' => $items,
        ]);
    }

    /**
     * @OA\Get(
     *     path="api/v1/shipments/{id}/items/count",
     *     tags={"Поставки"},
     *     description="Подсчитать кол-во элементов (товаров с одного склада одного мерчанта) отправления",
     *     @OA\Parameter(name="id", required=true, in="path", @OA\Schema(type="integer")),
     *      @OA\Parameter(name="include", required=false, in="query", @OA\Schema(type="array", @OA\Items(type="string")), description="параметр json-api запроса include"),
     *     @OA\Parameter(name="fields", required=false, in="query", @OA\Schema(type="array", @OA\Items(type="string")), description="параметр json-api запроса fields"),
     *     @OA\Parameter(name="filter", required=false, in="query", @OA\Schema(type="array", @OA\Items(type="string")), description="параметр json-api запроса filter"),
     *     @OA\Parameter(name="sort", required=false, in="query", @OA\Schema(type="array", @OA\Items(type="string")), description="параметр json-api запроса sort"),
     *     @OA\Parameter(name="page", required=false, in="query", @OA\Schema(type="array", @OA\Items(type="integer")), description="параметр json-api запроса page"),
     *     @OA\Response(
     *         response="200",
     *         description="",
     *         @OA\JsonContent(
     *             @OA\Property(property="total", type="string"),
     *             @OA\Property(property="pages", type="string"),
     *             @OA\Property(property="pageSize", type="string")
     *         )
     *     )
     * )
     *
     * Подсчитать кол-во элементов (товаров с одного склада одного мерчанта) отправления
     */
    public function countItems(int $shipmentId, Request $request): JsonResponse
    {
        /** @var Model|RestSerializable $modelClass */
        $modelClass = $this->modelItemsClass();
        $restQuery = new RestQuery($request);

        $pagination = $restQuery->getPage();
        $pageSize = $pagination ? $pagination['limit'] : ReadAction::$PAGE_SIZE;
        $baseQuery = $modelClass::query();

        $query = $modelClass::modifyQuery($baseQuery->where('shipment_id', $shipmentId), $restQuery);
        $total = $query->count();

        $pages = ceil($total / $pageSize);

        return response()->json([
            'total' => $total,
            'pages' => $pages,
            'pageSize' => $pageSize,
        ]);
    }

    /**
     * @OA\Get(
     *     path="api/v1/shipments/{id}/items",
     *     tags={"Поставки"},
     *     description="Список элементов (товаров с одного склада одного мерчанта) отправления",
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
     *             @OA\Property(property="items", type="array", @OA\Items(ref="#/components/schemas/ShipmentItem")),
     *         )
     *     )
     * )
     *
     * Список элементов (товаров с одного склада одного мерчанта) отправления
     */
    public function readItems(int $shipmentId, Request $request): JsonResponse
    {
        /** @var Model|RestSerializable $modelClass */
        $modelClass = $this->modelItemsClass();
        $restQuery = new RestQuery($request);

        $pagination = $restQuery->getPage();
        $baseQuery = $modelClass::query();
        if ($pagination) {
            $baseQuery->offset($pagination['offset'])->limit($pagination['limit']);
        }
        $query = $modelClass::modifyQuery($baseQuery->where('shipment_id', $shipmentId), $restQuery);

        $items = $query->get()
            ->map(function (RestSerializable $model) use ($restQuery) {
                return $model->toRest($restQuery);
            });

        return response()->json([
            'items' => $items,
        ]);
    }

    /**
     * @OA\Get(
     *     path=" api/v1/shipments/{id}/items/{basketItemId}",
     *     tags={"Поставки"},
     *     description="Информация об элементе (товар с одного склада одного мерчанта) отправления",
     *     @OA\Parameter(name="id", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="basketItemId", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="include", required=false, in="query", @OA\Schema(type="array", @OA\Items(type="string")), description="параметр json-api запроса include"),
     *     @OA\Parameter(name="fields", required=false, in="query", @OA\Schema(type="array", @OA\Items(type="string")), description="параметр json-api запроса fields"),
     *     @OA\Parameter(name="filter", required=false, in="query", @OA\Schema(type="array", @OA\Items(type="string")), description="параметр json-api запроса filter"),
     *     @OA\Parameter(name="sort", required=false, in="query", @OA\Schema(type="array", @OA\Items(type="string")), description="параметр json-api запроса sort"),
     *     @OA\Parameter(name="page", required=false, in="query", @OA\Schema(type="array", @OA\Items(type="integer")), description="параметр json-api запроса page"),
     *     @OA\Response(
     *         response="200",
     *         description="",
     *         @OA\JsonContent(
     *             @OA\Property(property="items", type="array", @OA\Items(ref="#/components/schemas/ShipmentItem")),
     *         )
     *     )
     * )
     *
     * Информация об элементе (товар с одного склада одного мерчанта) отправления
     */
    public function readItem(int $shipmentId, int $basketItemId, Request $request): JsonResponse
    {
        /** @var Model|RestSerializable $modelClass */
        $modelClass = $this->modelItemsClass();
        $restQuery = new RestQuery($request);
        $baseQuery = $modelClass::query()
            ->where('shipment_id', $shipmentId)
            ->where('basket_item_id', $basketItemId);
        $query = $modelClass::modifyQuery($baseQuery, $restQuery);

        /** @var RestSerializable $model */
        $model = $query->first();
        if (!$model) {
            throw new NotFoundHttpException();
        }

        return response()->json([
            'items' => $model->toRest($restQuery),
        ]);
    }

    /**
     *  @OA\Post (
     *     path=" api/v1/shipments/{id}/items/{basketItemId}",
     *     tags={"Корзина"},
     *     description="Создать элемент (товар с одного склада одного мерчанта) отправления",
     *     @OA\Parameter(name="id", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="basketItemId", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\Response(response="204", description=""),
     *     @OA\Response(response="404", description="shipment not found"),
     *     @OA\Response(response="500", description="unable to save shipment item"),
     * )
     * Создать элемент (товар с одного склада одного мерчанта) отправления
     */
    public function createItem(int $shipmentId, int $basketItemId, ShipmentService $shipmentService): Response
    {
        $shipmentService->getShipment($shipmentId);

        $shipmentItem = new ShipmentItem();
        $shipmentItem->shipment_id = $shipmentId;
        $shipmentItem->basket_item_id = $basketItemId;
        $ok = $shipmentItem->save();
        if (!$ok) {
            throw new HttpException(500, 'unable to save shipment item');
        }

        return response('', 201);
    }

    /**
     * @OA\Delete(
     *     path="api/v1/shipments/{id}/items/{basketItemId}",
     *     tags={"Корзина"},
     *     description="Удалить элемент (товар с одного склада одного мерчанта) отправления",
     *     @OA\Parameter(name="id", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="basketItemId", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\Response(response="204", description=""),
     *     @OA\Response(response="404", description="shipment item not found"),
     * )
     * Удалить элемент (товар с одного склада одного мерчанта) отправления
     */
    public function deleteItem(int $shipmentId, int $basketItemId): Response
    {
        /** @var ShipmentItem $shipmentItem */
        $shipmentItem = ShipmentItem::query()
            ->where('shipment_id', $shipmentId)
            ->where('basket_item_id', $basketItemId)
            ->first();
        if (!$shipmentItem) {
            throw new NotFoundHttpException('shipment item not found');
        }

        try {
            $ok = $shipmentItem->delete();
        } catch (\Throwable $e) {
            $ok = false;
            report($e);
        }

        if (!$ok) {
            throw new HttpException(500);
        }

        return response('', 204);
    }

    /**
     * @OA\Put (
     *     path="api/v1/shipments/{id}/items/{basketItemId}",
     *     tags={"Поставки"},
     *     description="Отменить поштучно элемент (собранный товар с одного склада одного мерчанта) отправления",
     *     @OA\Parameter(name="id", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="basketItemId", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *      required=true,
     *      description="",
     *      @OA\JsonContent(
     *          @OA\Property(property="qty", type="number"),
     *          @OA\Property(property="canceled_by", type="integer"),
     *          @OA\Property(property="return_reason_id", type="integer"),
     *      ),
     *     ),
     *     @OA\Response(response="204", description=""),
     *     @OA\Response(response="500", description="bad request")
     * )
     * Отменить поштучно элемент (товар с одного склада одного мерчанта) отправления
     */
    public function cancelItem(
        int $shipmentId,
        int $basketItemId,
        Request $request,
        DeliveryService $deliveryService
    ): Response {
        $data = $this->validate($request, [
            'qty' => ['required', 'numeric'],
            'canceled_by' => ['required', 'integer'],
            'return_reason_id' => ['required', 'integer'],
        ]);

        try {
            $deliveryService->cancelShipmentItem(
                $shipmentId,
                $basketItemId,
                $data['qty'],
                $data['canceled_by'],
                $data['return_reason_id'],
            );

            return response('', 204);
        } catch (\Throwable $e) {
            throw new HttpException(500, $e->getMessage());
        }
    }

    /**
     * @OA\Get(
     *     path="api/v1/shipments/{id}/barcodes",
     *     tags={"Поставки"},
     *     description="Получить штрихкоды для мест (коробок) отправления",
     *     @OA\Parameter(name="id", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response="200",
     *         description="",
     *         @OA\JsonContent(
     *             @OA\Property(property="absolute_url", type="string"),
     *             @OA\Property(property="original_name", type="string"),
     *             @OA\Property(property="size", type="string")
     *         )
     *     )
     * )
     *
     * Получить штрихкоды для мест (коробок) отправления
     */
    public function barcodes(int $id, ShipmentService $shipmentService, FileService $fileService): JsonResponse
    {
        $shipment = $shipmentService->getShipment($id);
        $deliveryOrderBarcodesDto = $shipmentService->getShipmentBarcodes($shipment);

        if ($deliveryOrderBarcodesDto) {
            if ($deliveryOrderBarcodesDto->success && $deliveryOrderBarcodesDto->file_id) {
                /** @var FileDto $fileDto */
                $fileDto = $fileService->getFiles([$deliveryOrderBarcodesDto->file_id])->first();

                return response()->json([
                    'absolute_url' => $fileDto->absoluteUrl(),
                    'original_name' => "barcode-{$shipment->number}.pdf", //$fileDto->original_name,
                    'size' => $fileDto->size,
                ]);
            } else {
                throw new HttpException(500, $deliveryOrderBarcodesDto->message);
            }
        }

        throw new HttpException(500);
    }

    /**
     * @OA\Get(
     *     path="api/v1/shipments/{id}/cdek-receipt",
     *     tags={"Поставки"},
     *     description="Получить квитанцию cdek для заказа на доставку",
     *     @OA\Parameter(name="id", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response="200",
     *         description="",
     *         @OA\JsonContent(
     *             @OA\Property(property="absolute_url", type="string"),
     *             @OA\Property(property="original_name", type="string"),
     *             @OA\Property(property="size", type="string")
     *         )
     *     )
     * )
     * Получить квитанцию cdek для заказа на доставку
     */
    public function cdekReceipt(int $id, ShipmentService $shipmentService, FileService $fileService): JsonResponse
    {
        $shipment = $shipmentService->getShipment($id);
        $cdekDeliveryOrderReceiptDto = $shipmentService->getShipmentCdekReceipt($shipment);

        if ($cdekDeliveryOrderReceiptDto) {
            if ($cdekDeliveryOrderReceiptDto->success && $cdekDeliveryOrderReceiptDto->file_id) {
                /** @var FileDto $fileDto */
                $fileDto = $fileService->getFiles([$cdekDeliveryOrderReceiptDto->file_id])->first();

                return response()->json([
                    'absolute_url' => $fileDto->absoluteUrl(),
                    'original_name' => $fileDto->original_name,
                    'size' => $fileDto->size,
                ]);
            } else {
                throw new HttpException(500, $cdekDeliveryOrderReceiptDto->message);
            }
        }

        throw new HttpException(500);
    }

    /**
     * @OA\Put(
     *     path="api/v1/shipments/{id}/mark-as-problem",
     *     tags={"Поставки"},
     *     description="Пометить как проблемное",
     *     @OA\Parameter(name="id", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\Response(response="204", description=""),
     *     @OA\Response(response="404", description="shipment not found"),
     *     @OA\Response(response="500", description="bad request"),
     * )
     *
     * Пометить как проблемное
     */
    public function markAsProblem(int $id, Request $request, ShipmentService $shipmentService): Response
    {
        $shipment = $shipmentService->getShipment($id);
        $data = $this->validate($request, [
            'assembly_problem_comment' => ['required'],
        ]);

        if (!$shipmentService->markAsProblemShipment($shipment, $data['assembly_problem_comment'])) {
            throw new HttpException(500);
        }

        return response('', 204);
    }

    /**
     * @OA\Put(
     *     path="api/v1/shipments/{id}/mark-as-non-problem",
     *     tags={"Поставки"},
     *     description="Пометить как непроблемное",
     *     @OA\Parameter(name="id", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\Response(response="204", description=""),
     *     @OA\Response(response="404", description="shipment not found"),
     *     @OA\Response(response="500", description="bad request"),
     * )
     *
     * Пометить как непроблемное
     */
    public function markAsNonProblem(int $id, ShipmentService $shipmentService): Response
    {
        $shipment = $shipmentService->getShipment($id);
        if (!$shipmentService->markAsNonProblemShipment($shipment)) {
            throw new HttpException(500);
        }

        return response('', 204);
    }

    /**
     * @OA\Put(
     *     path="api/v1/shipments/{id}/cancel",
     *     tags={"Поставки"},
     *     description="Отменить отправление",
     *     @OA\Parameter(name="id", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\Response(response="204", description=""),
     *     @OA\Response(response="404", description="product not found"),
     * )
     * Отменить отправление
     * @throws Exception
     */
    public function cancel(int $id, Request $request, ShipmentService $shipmentService): Response
    {
        $data = $this->validate($request, [
            'orderReturnReason' => 'required|integer|exists:order_return_reasons,id',
        ]);

        $shipment = $shipmentService->getShipment($id);
        if (!$shipmentService->cancelShipment($shipment, $data['orderReturnReason'])) {
            throw new HttpException(500);
        }

        return response('', 204);
    }

    /**
     * @OA\Get(
     *     path="api/v1/shipments/exports/new",
     *     tags={"Поставки"},
     *     description="",
     *     @OA\RequestBody(
     *      required=true,
     *      description="",
     *      @OA\JsonContent(
     *          required={"filter['merchant_id']"},
     *          @OA\Property(property="filter['merchant_id']", type="integer"),
     *      ),
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="",
     *         @OA\JsonContent(
     *             @OA\Property(property="items", type="array", @OA\Items(ref="#/components/schemas/Shipment"))
     *         )
     *     )
     * )
     */
    public function readNew(Request $request): JsonResponse
    {
        $restQuery = new RestQuery($request);

        $this->validate($request, [
            'filter.merchant_id' => 'required|int',
        ]);

        /** @var Model|RestSerializable $modelClass */
        $modelClass = $this->modelClass();
        $query = $modelClass::modifyQuery($modelClass::query(), $restQuery);

        $items = $query
            ->with([
                'basketItems' => function ($q) {
                    $q->active();
                },
                'delivery.order',
            ])
            ->where('status', '>=', ShipmentStatus::AWAITING_CONFIRMATION)
            ->where(
                function (Builder $builder) {
                    $builder->whereIn('payment_status', [PaymentStatus::HOLD, PaymentStatus::PAID])
                        ->orWhereHas('delivery.order', function (Builder $builder) {
                            $builder->where('payment_status', PaymentStatus::WAITING)
                                ->where('is_postpaid', true);
                        });
                }
            )
            ->where('is_canceled', false)
            ->doesntHave('exports')
            ->get();

        return response()->json([
            'items' => $items,
        ]);
    }

    /**
     * @OA\Post(
     *     path="api/v1/shipments/exports",
     *     tags={"Поставки"},
     *     description="",
     *     @OA\RequestBody(
     *      required=true,
     *      description="",
     *      @OA\JsonContent(
     *          required={"shipment_id","merchant_integration_id"},
     *          @OA\Property(property="shipment_id", type="integer"),
     *          @OA\Property(property="merchant_integration_id", type="integer"),
     *          @OA\Property(property="shipment_xml_id", type="string"),
     *          @OA\Property(property="err_code", type="integer"),
     *          @OA\Property(property="err_message", type="string"),
     *      ),
     *     ),
     *     @OA\Response(
     *          response="200",
     *          description="",
     *          @OA\JsonContent(
     *              @OA\Property(property="id", type="string"),
     *          )
     *     ),
     *     @OA\Response(response="400", description="Ошибка валидации"),
     *     @OA\Response(response="404", description=""),
     *     @OA\Response(response="500", description="Не удалось сохранить данные"),
     * )
     */
    public function createShipmentExport(Request $request): JsonResponse
    {
        $data = $this->validate($request, [
            'shipment_id' => 'required|int',
            'merchant_integration_id' => 'required|int',
            'shipment_xml_id' => 'nullable|string',
            'err_code' => 'nullable|int',
            'err_message' => 'nullable|string',
        ]);

        /** @var ShipmentExport $shipmentExport */
        $shipmentExport = ShipmentExport::whereNull('shipment_xml_id')
            ->where('shipment_id', $data['shipment_id'])
            ->where('merchant_integration_id', $data['merchant_integration_id'])
            ->first();

        if (!is_null($shipmentExport)) {
            $shipmentExport->shipment_xml_id = $data['shipment_xml_id'];
            $shipmentExport->err_code = $data['err_code'];
            $shipmentExport->err_message = $data['err_message'];

            $shipmentExport->save();
        } else {
            $shipmentExport = ShipmentExport::create($data);
        }

        return response()->json([
            'id' => $shipmentExport->id,
        ], 201);
    }
}
