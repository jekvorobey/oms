<?php

namespace App\Http\Controllers\V1\Delivery;

use App\Http\Controllers\Controller;
use App\Models\Delivery\Shipment;
use App\Models\Delivery\ShipmentPackage;
use App\Models\Delivery\ShipmentPackageItem;
use App\Services\DeliveryService;
use Greensight\CommonMsa\Rest\Controller\ReadAction;
use Greensight\CommonMsa\Rest\Controller\UpdateAction;
use Greensight\CommonMsa\Rest\Controller\Validation\RequiredOnPost;
use Greensight\CommonMsa\Rest\RestQuery;
use Greensight\CommonMsa\Rest\RestSerializable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class ShipmentPackagesController
 * @package App\Http\Controllers\V1\Delivery
 */
class ShipmentPackagesController extends Controller
{
    /**
     * @OA\Get(
     *     path="api/v1/shipment-packages",
     *     tags={"Посылки"},
     *     description="Получить список посылок",
     *     @OA\Parameter(name="include", required=false, in="query", @OA\Schema(type="array", @OA\Items(type="string")), description="параметр json-api запроса include"),
     *     @OA\Parameter(name="fields", required=false, in="query", @OA\Schema(type="array", @OA\Items(type="string")), description="параметр json-api запроса fields"),
     *     @OA\Parameter(name="filter", required=false, in="query", @OA\Schema(type="array", @OA\Items(type="string")), description="параметр json-api запроса filter"),
     *     @OA\Parameter(name="sort", required=false, in="query", @OA\Schema(type="array", @OA\Items(type="string")), description="параметр json-api запроса sort"),
     *     @OA\Parameter(name="page", required=false, in="query", @OA\Schema(type="array", @OA\Items(type="integer")), description="параметр json-api запроса page"),
     *     @OA\Response(
     *         response="200",
     *         description="",
     *         @OA\JsonContent(
     *             @OA\Property(property="items", type="array", @OA\Items(ref="#/components/schemas/ShipmentPackage"))
     *         )
     *     )
     * )
     * @OA\Get(
     *     path="api/v1/shipment-packages/{id}",
     *     tags={"Посылки"},
     *     description="Получить посылку с ID",
     *     @OA\Parameter(name="id", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="include", required=false, in="query", @OA\Schema(type="array", @OA\Items(type="string")), description="параметр json-api запроса include"),
     *     @OA\Parameter(name="fields", required=false, in="query", @OA\Schema(type="array", @OA\Items(type="string")), description="параметр json-api запроса fields"),
     *     @OA\Parameter(name="filter", required=false, in="query", @OA\Schema(type="array", @OA\Items(type="string")), description="параметр json-api запроса filter"),
     *     @OA\Parameter(name="sort", required=false, in="query", @OA\Schema(type="array", @OA\Items(type="string")), description="параметр json-api запроса sort"),
     *     @OA\Parameter(name="page", required=false, in="query", @OA\Schema(type="array", @OA\Items(type="integer")), description="параметр json-api запроса page"),
     *     @OA\Response(
     *         response="200",
     *         description="",
     *         @OA\JsonContent(ref="#/components/schemas/ShipmentPackage")
     *     )
     * )
     */
    use ReadAction;

    /**
     * @OA\Put(
     *     path="api/v1/shipment-packages/{id}",
     *     tags={"Посылки"},
     *     description="Обновить значения посылки.",
     *     @OA\Parameter(name="id", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *      required=true,
     *      description="Изменить значение посылки.",
     *          @OA\JsonContent(ref="#/components/schemas/ShipmentPackage")
     *     ),
     *     @OA\Response(response="204", description="Данные сохранены"),
     *     @OA\Response(response="404", description="not found"),
     * )
     */
    use UpdateAction;

    /**
     * @inheritDoc
     */
    public function modelClass(): string
    {
        return ShipmentPackage::class;
    }

    /**
     * @inheritDoc
     */
    public function modelItemsClass(): string
    {
        return ShipmentPackageItem::class;
    }

    /**
     * @inheritDoc
     */
    protected function writableFieldList(): array
    {
        return ShipmentPackage::FILLABLE;
    }

    /**
     * @return array
     */
    protected function inputValidators(): array
    {
        return [
            'package_id' => [new RequiredOnPost(), 'integer'],
            'width' => [new RequiredOnPost(), 'numeric'],
            'height' => [new RequiredOnPost(), 'numeric'],
            'length' => [new RequiredOnPost(), 'numeric'],
            'wrapper_weight' => [new RequiredOnPost(), 'numeric'],
        ];
    }

    /**
     * @OA\Get(
     *     path="api/v1/shipment-packages/{id}/items/count",
     *     tags={"Посылки"},
     *     description="Подсчитать кол-во коробок отправления",
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
     * Подсчитать кол-во коробок отправления
     * @param int $shipmentId
     * @param  Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function countByShipment(int $shipmentId, Request $request): JsonResponse
    {
        /** @var Model|RestSerializable $modelClass */
        $modelClass = $this->modelClass();
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
     * @OA\Post(
     *     path="api/v1/shipments/{id}/shipment-packages",
     *     tags={"Посылки"},
     *     description="Добавить посылку",
     *     @OA\Parameter(name="id", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *      required=true,
     *      description="",
     *      @OA\JsonContent(
     *          @OA\Property(property="package_id", type="integer"),
     *          @OA\Property(property="width", type="number"),
     *          @OA\Property(property="height", type="number"),
     *          @OA\Property(property="length", type="number"),
     *          @OA\Property(property="wrapper_weight", type="number"),
     *      ),
     *     ),
     *     @OA\Response(
     *         response="201",
     *         description="",
     *         @OA\JsonContent(
     *             @OA\Property(property="total", type="integer"),
     *         )
     *     ),
     *     @OA\Response(response="400", description="Ошибка валидации"),
     *     @OA\Response(response="404", description=""),
     *     @OA\Response(response="500", description="Не удалось сохранить данные"),
     * )
     * Создать коробку отправления
     * @param  int  $shipmentId
     * @param  Request  $request
     * @return JsonResponse
     */
    public function create(int $shipmentId, Request $request): JsonResponse
    {
        /** @var Shipment $shipment */
        $shipment = Shipment::find($shipmentId);
        if (!$shipment) {
            throw new NotFoundHttpException('shipment not found');
        }

        $data = $request->all();
        $validator = Validator::make($data, $this->inputValidators());
        if ($validator->fails()) {
            throw new BadRequestHttpException($validator->errors()->first());
        }
        $data['shipment_id'] = $shipmentId;

        $shipmentPackage = new ShipmentPackage($data);
        $ok = $shipmentPackage->save();
        if (!$ok) {
            throw new HttpException(500, 'unable to save shipment package');
        }

        return response()->json([
            'id' => $shipmentPackage->id
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="api/v1/shipments/{id}/shipment-packages",
     *     tags={"Посылки"},
     *     description="Список коробок отправления",
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
     *             @OA\Property(property="items", type="array", @OA\Items(ref="#/components/schemas/ShipmentPackage"))
     *         )
     *     )
     * )
     *
     * Список коробок отправления
     * @param  int  $shipmentId
     * @param  Request  $request
     * @return JsonResponse
     */
    public function readByShipment(int $shipmentId, Request $request): JsonResponse
    {
        /** @var Model|RestSerializable $modelClass */
        $modelClass = $this->modelClass();
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
            'items' => $items
        ]);
    }

    /**
     * @OA\Delete(
     *     path="api/v1/shipment-packages/{id}",
     *     tags={"Посылки"},
     *     description="Удалить посылку",
     *     @OA\Parameter(name="id", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\Response(response="204", description=""),
     *     @OA\Response(response="404", description="Сущность не найдена"),
     *     @OA\Response(response="500", description="Не удалось удалить сущность"),
     * )
     *
     * Удалить коробку отправления со всем её содержимым
     * @param  int  $id
     * @param DeliveryService $deliveryService
     * @return Response
     */
    public function delete(int $id, DeliveryService $deliveryService): Response
    {
        try {
            $ok = $deliveryService->deleteShipmentPackage($id);
            if (!$ok) {
                throw new HttpException(500);
            }

            return response('', 204);
        } catch (\Exception $e) {
            throw new HttpException(500, $e->getMessage());
        }
    }

    /**
     * @OA\Get(
     *     path="api/v1/shipments/{id}/shipment-packages/count",
     *     tags={"Посылки"},
     *     description="Подсчитать кол-во элементов (товаров с одного склада одного мерчанта) коробки отправления",
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
     * Подсчитать кол-во элементов (товаров с одного склада одного мерчанта) коробки отправления
     * @param  int  $shipmentPackageId
     * @param  Request  $request
     * @return JsonResponse
     */
    public function countItems(int $shipmentPackageId, Request $request): JsonResponse
    {
        /** @var Model|RestSerializable $modelClass */
        $modelClass = $this->modelItemsClass();
        $restQuery = new RestQuery($request);

        $pagination = $restQuery->getPage();
        $pageSize = $pagination ? $pagination['limit'] : ReadAction::$PAGE_SIZE;
        $baseQuery = $modelClass::query();

        $query = $modelClass::modifyQuery($baseQuery->where('shipment_package_id', $shipmentPackageId), $restQuery);
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
     *     path="api/v1/shipment-packages/{id}/items",
     *     tags={"Посылки"},
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
     *             @OA\Property(property="items", type="array", @OA\Items(ref="#/components/schemas/ShipmentPackage"))
     *         )
     *     )
     * )
     * Список элементов (товаров с одного склада одного мерчанта) отправления
     * @param  int  $shipmentPackageId
     * @param  Request  $request
     * @return JsonResponse
     */
    public function readItems(int $shipmentPackageId, Request $request): JsonResponse
    {
        /** @var Model|RestSerializable $modelClass */
        $modelClass = $this->modelItemsClass();
        $restQuery = new RestQuery($request);

        $pagination = $restQuery->getPage();
        $baseQuery = $modelClass::query();
        if ($pagination) {
            $baseQuery->offset($pagination['offset'])->limit($pagination['limit']);
        }
        $query = $modelClass::modifyQuery($baseQuery->where('shipment_package_id', $shipmentPackageId), $restQuery);

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
     *     path="api/v1/shipment-packages/{id}/items/{basketItemId}",
     *     tags={"Посылки"},
     *     description="Информация об элементе (товар с одного склада одного мерчанта) коробки отправления",
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
     *             @OA\Property(property="items", type="array", @OA\Items(ref="#/components/schemas/ShipmentPackage"))
     *         )
     *     )
     * )
     * Информация об элементе (товар с одного склада одного мерчанта) коробки отправления
     * @param  int  $shipmentPackageId
     * @param  int  $basketItemId
     * @param  Request  $request
     * @return JsonResponse
     */
    public function readItem(int $shipmentPackageId, int $basketItemId, Request $request): JsonResponse
    {
        /** @var Model|RestSerializable $modelClass */
        $modelClass = $this->modelItemsClass();
        $restQuery = new RestQuery($request);
        $baseQuery = $modelClass::query()
            ->where('shipment_package_id', $shipmentPackageId)
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
     * @OA\Put (
     *     path="api/v1/shipment-packages/{id}/items/{basketItemId}",
     *     tags={"Посылки"},
     *     description="Добавить/обновить/удалить элемент (собранный товар с одного склада одного мерчанта) коробки отправления",
     *     @OA\Parameter(name="id", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="basketItemId", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *      required=true,
     *      description="",
     *      @OA\JsonContent(
     *          @OA\Property(property="qty", type="number"),
     *          @OA\Property(property="set_by", type="integer"),
     *      ),
     *     ),
     *     @OA\Response(response="204", description=""),
     *     @OA\Response(response="500", description="bad request")
     * )
     * Добавить/обновить/удалить элемент (собранный товар с одного склада одного мерчанта) коробки отправления
     * @param  int  $shipmentPackageId
     * @param  int  $basketItemId
     * @param  Request  $request
     * @param  DeliveryService  $deliveryService
     * @return Response
     */
    public function setItem(int $shipmentPackageId, int $basketItemId, Request $request, DeliveryService $deliveryService): Response
    {
        $data = $this->validate($request, [
            'qty' => ['required', 'numeric'],
            'set_by' => ['required', 'integer'],
        ]);

        try {
            $ok = $deliveryService->setShipmentPackageItem(
                $shipmentPackageId,
                $basketItemId,
                $data['qty'],
                $data['set_by']
            );
            if (!$ok) {
                throw new HttpException(500);
            }

            return response('', 204);
        } catch (\Exception $e) {
            throw new HttpException(500, $e->getMessage());
        }
    }
}
