<?php

namespace App\Http\Controllers\V1\Delivery;

use App\Http\Controllers\Controller;
use App\Models\Delivery\Delivery;
use App\Models\Delivery\DeliveryStatus;
use App\Models\Order\Order;
use App\Services\DeliveryService as OmsDeliveryService;
use Greensight\CommonMsa\Rest\Controller\CountAction;
use Greensight\CommonMsa\Rest\Controller\DeleteAction;
use Greensight\CommonMsa\Rest\Controller\ReadAction;
use Greensight\CommonMsa\Rest\Controller\UpdateAction;
use Greensight\CommonMsa\Rest\Controller\Validation\RequiredOnPost;
use Greensight\CommonMsa\Rest\RestQuery;
use Greensight\CommonMsa\Rest\RestSerializable;
use Greensight\Logistics\Dto\Lists\DeliveryMethod;
use Greensight\Logistics\Dto\Lists\DeliveryService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class DeliveryController
 * @package App\Http\Controllers\V1\Delivery
 */

class DeliveryController extends Controller
{
    /**
     * @OA\Get(
     *     path="api/v1/deliveries/count",
     *     tags={"Дотсавка"},
     *     description="Количество сущностей вариантов значений доставка",
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
     *     path="api/v1/deliveries",
     *     tags={"Дотсавка"},
     *     description="Получить список доставок",
     *     @OA\Parameter(name="include", required=false, in="query", @OA\Schema(type="array", @OA\Items(type="string")), description="параметр json-api запроса include"),
     *     @OA\Parameter(name="fields", required=false, in="query", @OA\Schema(type="array", @OA\Items(type="string")), description="параметр json-api запроса fields"),
     *     @OA\Parameter(name="filter", required=false, in="query", @OA\Schema(type="array", @OA\Items(type="string")), description="параметр json-api запроса filter"),
     *     @OA\Parameter(name="sort", required=false, in="query", @OA\Schema(type="array", @OA\Items(type="string")), description="параметр json-api запроса sort"),
     *     @OA\Parameter(name="page", required=false, in="query", @OA\Schema(type="array", @OA\Items(type="integer")), description="параметр json-api запроса page"),
     *     @OA\Response(
     *         response="200",
     *         description="",
     *         @OA\JsonContent(
     *             @OA\Property(property="items", type="array", @OA\Items(ref="#/components/schemas/Delivery"))
     *         )
     *     )
     * )
     *
     * @OA\Get(
     *     path="api/v1/deliveries/{id}",
     *     tags={"Дотсавка"},
     *     description="Получить значение доставки с ID",
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
     *             @OA\Property(property="items", type="array", @OA\Items(ref="#/components/schemas/Delivery"))
     *         )
     *     )
     * )
     */
    use ReadAction;

    /**
     * @OA\Put(
     *     path="api/v1/deliveries/{id}",
     *     tags={"Дотсавка"},
     *     description="Изменить значения значение доставки с ID.",
     *     @OA\Parameter(name="id", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *      required=true,
     *      description="Изменить значение для public event types.",
     *          @OA\JsonContent(ref="#/components/schemas/Delivery")
     *     ),
     *     @OA\Response(response="204", description="Данные сохранены"),
     *     @OA\Response(response="404", description="product not found"),
     * )
     */
    use UpdateAction;

    /**
     * @OA\Delete(
     *     path="api/v1/deliveries/{id}",
     *     tags={"Дотсавка"},
     *     description="Удалить доставку",
     *     @OA\Parameter(name="id", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\Response(response="204", description=""),
     *     @OA\Response(response="404", description="Сущность не найдена"),
     *     @OA\Response(response="500", description="Не удалось удалить сущность"),
     * )
     */
    use DeleteAction;

    public function modelClass(): string
    {
        return Delivery::class;
    }

    /**
     * @inheritDoc
     */
    protected function writableFieldList(): array
    {
        return Delivery::FILLABLE;
    }

    /**
     * @return array
     */
    protected function inputValidators(): array
    {
        return [
            'status' => ['nullable', Rule::in(DeliveryStatus::validValues())],
            'delivery_method' => [new RequiredOnPost(), Rule::in(array_keys(DeliveryMethod::allMethods()))],
            'delivery_service' => [new RequiredOnPost(), Rule::in(array_keys(DeliveryService::allServices()))],
            'xml_id' => ['nullable', 'string'],
            'tariff_id' => ['nullable', 'integer'],
            'point_id' => ['nullable', 'integer'],
            'number' => [new RequiredOnPost(), 'string'],
            'delivery_at' => [new RequiredOnPost(), 'date'],
            'receiver_name' => ['nullable', 'string'],
            'receiver_phone' => ['nullable', 'regex:/\+\d\(\d\d\d\)\s\d\d\d-\d\d-\d\d/'],
            'receiver_email' => ['nullable', 'email'],
            'delivery_address' => ['nullable', 'array'],
            'delivery_address.country_code' => ['string', 'nullable'],
            'delivery_address.post_index' => ['string', 'nullable'],
            'delivery_address.region' => ['string', 'nullable'],
            'delivery_address.region_guid' => ['string', 'nullable'],
            'delivery_address.city' => ['string', 'nullable'],
            'delivery_address.city_guid' => ['string', 'nullable'],
            'delivery_address.street' => ['sometimes', 'string', 'nullable'],
            'delivery_address.house' => ['sometimes', 'string', 'nullable'],
            'delivery_address.block' => ['sometimes', 'string', 'nullable'],
            'delivery_address.flat' => ['sometimes', 'string', 'nullable'],
            'delivery_address.porch' => ['sometimes', 'string', 'nullable'],
            'delivery_address.floor' => ['sometimes', 'string', 'nullable'],
            'delivery_address.intercom' => ['sometimes', 'string', 'nullable'],
            'delivery_address.comment' => ['sometimes', 'string', 'nullable'],
        ];
    }

    /**
     * @OA\Get(
     *     path="api/v1/orders/{id}/deliveries/count",
     *     tags={"Дотсавка"},
     *     description="Подсчитать кол-во доставок заказа",
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
     *
     * Подсчитать кол-во доставок заказа
     */
    public function countByOrder(int $orderId, Request $request): JsonResponse
    {
        /** @var Model|RestSerializable $modelClass */
        $modelClass = $this->modelClass();
        $restQuery = new RestQuery($request);


        $pagination = $restQuery->getPage();
        $pageSize = $pagination ? $pagination['limit'] : ReadAction::$PAGE_SIZE;
        $baseQuery = $modelClass::query();

        $query = $modelClass::modifyQuery($baseQuery->where('order_id', $orderId), $restQuery);
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
     *     path="api/v1/orders/{id}/deliveries",
     *     tags={"Дотсавка"},
     *     description="Создать доставку",
     *     @OA\RequestBody(
     *      required=true,
     *      description="",
     *      @OA\JsonContent(
     *          required={"name"},
     *          @OA\Property(property="status", type="integer"),
     *          @OA\Property(property="delivery_method", type="integer"),
     *          @OA\Property(property="delivery_service", type="integer"),
     *          @OA\Property(property="xml_id", type="string"),
     *          @OA\Property(property="tariff_id", type="integer"),
     *          @OA\Property(property="point_id", type="integer"),
     *          @OA\Property(property="number", type="string"),
     *          @OA\Property(property="delivery_at", type="string"),
     *          @OA\Property(property="receiver_name", type="string"),
     *          @OA\Property(property="receiver_phone", type="string"),
     *          @OA\Property(property="receiver_email", type="string"),
     *          @OA\Property(property="delivery_address['country_code']", type="string"),
     *          @OA\Property(property="delivery_address['post_index']", type="string"),
     *          @OA\Property(property="delivery_address['region']", type="string"),
     *          @OA\Property(property="delivery_address['region_guid']", type="string"),
     *          @OA\Property(property="delivery_address['city']", type="string"),
     *          @OA\Property(property="delivery_address['city_guid']", type="string"),
     *          @OA\Property(property="delivery_address['street']", type="string"),
     *          @OA\Property(property="delivery_address['house']", type="string"),
     *          @OA\Property(property="delivery_address['block']", type="string"),
     *          @OA\Property(property="delivery_address['flat']", type="string"),
     *          @OA\Property(property="delivery_address['porch']", type="string"),
     *          @OA\Property(property="delivery_address['floor']", type="string"),
     *          @OA\Property(property="delivery_address['intercom']", type="string"),
     *          @OA\Property(property="delivery_address['comment']", type="string"),
     *      ),
     *     ),
     *     @OA\Response(
     *         response="201",
     *         description="",
     *         @OA\JsonContent(
     *          @OA\Property(property="id", type="integer"),
     *         )
     *     ),
     *     @OA\Response(response="400", description="Bad request"),
     *     @OA\Response(response="500", description="unable to save delivery"),
     * )
     * Создать доставку
     */
    public function create(int $orderId, Request $request): JsonResponse
    {
        /** @var Order $order */
        $order = Order::find($orderId);
        if (!$order) {
            throw new NotFoundHttpException('order not found');
        }

        $data = $request->all();
        $validator = Validator::make($data, $this->inputValidators());
        if ($validator->fails()) {
            throw new BadRequestHttpException($validator->errors()->first());
        }
        $data['order_id'] = $orderId;

        $delivery = new Delivery($data);
        $ok = $delivery->save();
        if (!$ok) {
            throw new HttpException(500, 'unable to save delivery');
        }

        return response()->json([
            'id' => $delivery->id,
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="api/v1/orders/{id}/deliveries",
     *     tags={"Дотсавка"},
     *     description="Список доставок заказа",
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
     *             @OA\Property(property="items", type="array", @OA\Items(ref="#/components/schemas/Delivery"))
     *         )
     *     )
     * )
     * Список доставок заказа
     */
    public function readByOrder(int $orderId, Request $request): JsonResponse
    {
        /** @var Model|RestSerializable $modelClass */
        $modelClass = $this->modelClass();
        $restQuery = new RestQuery($request);

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

        return response()->json([
            'items' => $items,
        ]);
    }

    /**
     * @OA\Put(
     *     path="api/v1/deliveries/{id}/cancel",
     *     tags={"Дотсавка"},
     *     description="Отменить доставку.",
     *     @OA\Parameter(name="id", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\Response(response="204", description=""),
     *     @OA\Response(response="404", description="delivery not found"),
     * )
     * Отменить доставку
     * @throws \Exception
     */
    public function cancel(int $id, OmsDeliveryService $deliveryService): Response
    {
        $delivery = $deliveryService->getDelivery($id);
        if (!$delivery) {
            throw new NotFoundHttpException('delivery not found');
        }
        if (!$deliveryService->cancelDelivery($delivery)) {
            throw new HttpException(500);
        }

        return response('', 204);
    }

    /**
     * @OA\Put(
     *     path="api/v1/deliveries/{id}/delivery-order",
     *     tags={"Дотсавка"},
     *     description="Создать/обновить заказ на доставку у службы доставки.",
     *     @OA\Parameter(name="id", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\Response(response="204", description=""),
     *     @OA\Response(response="404", description="delivery not found"),
     * )
     * Создать/обновить заказ на доставку у службы доставки
     * @throws \Exception
     */
    public function saveDeliveryOrder(int $id, OmsDeliveryService $deliveryService): Response
    {
        $delivery = $deliveryService->getDelivery($id);
        if (!$delivery) {
            throw new NotFoundHttpException('delivery not found');
        }
        $deliveryService->saveDeliveryOrder($delivery);

        return response('', 204);
    }

    /**
     * @OA\Put(
     *     path="api/v1/deliveries/{id}/delivery-order/cancel",
     *     tags={"Дотсавка"},
     *     description="Отменить заказ на доставку у службы доставки.",
     *     @OA\Parameter(name="id", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\Response(response="204", description=""),
     *     @OA\Response(response="404", description="delivery not found"),
     * )
     *
     * Отменить заказ на доставку у службы доставки
     */
    public function cancelDeliveryOrder(int $id, OmsDeliveryService $deliveryService): Response
    {
        $delivery = $deliveryService->getDelivery($id);
        if (!$delivery) {
            throw new NotFoundHttpException('delivery not found');
        }
        $deliveryService->cancelDeliveryOrder($delivery);

        return response('', 204);
    }

    /**
     * Получить кол-во доставок по каждой службе доставки за сегодня
     */
    public function countTodayByDeliveryServices(): JsonResponse
    {
        $deliveries = Delivery::query()
            ->select('delivery_service', DB::raw('DATE(created_at) day'), DB::raw('count(*) as total'))
            ->whereDate('created_at', now()->setTime(0, 0))
            ->groupBy(['delivery_service', 'day'])
            ->get();

        return response()->json([
            'items' => $deliveries->map(function (Delivery $delivery) {
                return [
                    'delivery_service_id' => $delivery->delivery_service,
                    'qty_today' => $delivery['total'],
                ];
            }),
        ]);
    }
}
