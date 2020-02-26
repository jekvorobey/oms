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
use Greensight\CommonMsa\Services\RequestInitiator\RequestInitiator;
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
    use ReadAction {
        read as readTrait;
    }
    use UpdateAction {
        update as updateTrait;
    }
    
    /**
     * Получить класс модели в виде строки
     * Пример: return MyModel::class;
     * @return string
     */
    public function modelClass(): string
    {
        return ShipmentPackage::class;
    }
    
    /**
     * Получить класс модели элементов в виде строки
     * Пример: return MyModel::class;
     * @return string
     */
    public function modelItemsClass(): string
    {
        return ShipmentPackageItem::class;
    }
    
    /**
     * Задать права для выполнения стандартных rest действий.
     * Пример: return [ RestAction::$DELETE => 'permission' ];
     * @return array
     */
    public function permissionMap(): array
    {
        return [
            //todo Права доступа
        ];
    }
    
    /**
     * Получить список полей, которые можно редактировать через стандартные rest действия.
     * Пример return ['name', 'status'];
     * @return array
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
     * Создать коробку отправления
     * @param  int  $shipmentId
     * @param  Request  $request
     * @return JsonResponse
     * //todo swagger
     * @OA\Post(
     *     path="/api/v1/shipments/{id}/shipment-packages",
     *     tags={"shipment-package"},
     *     summary="Создать коробку отправления",
     *     operationId="createShipment",
     *     @OA\Parameter(description="ID доставки", in="path", name="id", required=true, @OA\Schema(type="integer")),
     *      @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="OK",
     *     ),
     * )
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
     * Информация о коробке отправления
     * @param  Request  $request
     * @param  RequestInitiator  $client
     * @return \Illuminate\Http\JsonResponse
     */
    public function read(Request $request, RequestInitiator $client): JsonResponse
    {
        return $this->readTrait($request, $client);
    }
    
    /**
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
     * Изменить коробку отправления
     * @param  int  $id
     * @param  Request  $request
     * @return \Illuminate\Contracts\Routing\ResponseFactory|Response
     * //todo swagger
     * @OA\Put(
     *     path="/api/v1/shipment-packages/{id}",
     *     tags={"shipment-package"},
     *     summary="Изменить коробку отправления",
     *     operationId="updateShipmentPackage",
     *     @OA\Parameter(description="ID коробки отправления", in="path", name="id", required=true, @OA\Schema(type="integer")),
     *      @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *         )
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="OK",
     *     ),
     * )
     */
    public function update(int $id, Request $request, RequestInitiator $client): Response
    {
        return $this->updateTrait($id, $request, $client);
    }
    
    /**
     * Удалить коробку отправления со всем её содержимым
     * @param  int  $id
     * @param DeliveryService $deliveryService
     * @return Response
     *
     * @OA\Delete(
     *     path="/api/v1/shipment-packages/{id}",
     *     tags={"shipment-package"},
     *     summary="Удалить коробку отправления",
     *     operationId="deleteShipmentPackage",
     *     @OA\Parameter(description="ID коробки отправления", in="path", name="id", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=204,
     *         description="OK",
     *     ),
     * )
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
