<?php

namespace App\Http\Controllers\V1\Delivery;

use App\Http\Controllers\Controller;
use App\Models\Basket\BasketItem;
use App\Models\Delivery\Shipment;
use App\Models\Delivery\ShipmentPackage;
use App\Models\Delivery\ShipmentPackageItem;
use App\Models\Delivery\ShipmentPackageStatus;
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
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
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
    use DeleteAction {
        delete as deleteTrait;
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
            'status' => ['nullable', Rule::in(ShipmentPackageStatus::validValues())],
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
     * @param  RequestInitiator  $client
     * @return \Illuminate\Http\JsonResponse
     */
    public function countByShipment(int $shipmentId, Request $request, RequestInitiator $client): JsonResponse
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
     *             //todo swagger
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
     * @param  RequestInitiator  $client
     * @return JsonResponse
     */
    public function readByShipment(int $shipmentId, Request $request, RequestInitiator $client): JsonResponse
    {
        //todo Проверка прав
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
     *
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
     *             //todo swagger
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
     * Удалить коробку отправления
     * @param  int  $id
     * @return \Illuminate\Contracts\Routing\ResponseFactory|Response
     * @throws \Exception
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
    public function delete(int $id, RequestInitiator $client): Response
    {
        return $this->deleteTrait($id, $client);
    }
    
    /**
     * Подсчитать кол-во элементов (товаров с одного склада одного мерчанта) коробки отправления
     * @param  int  $shipmentPackageId
     * @param  Request  $request
     * @param  RequestInitiator  $client
     * @return JsonResponse
     */
    public function countItems(int $shipmentPackageId, Request $request, RequestInitiator $client): JsonResponse
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
     * @param  RequestInitiator  $client
     * @return JsonResponse
     */
    public function readItems(int $shipmentPackageId, Request $request, RequestInitiator $client): JsonResponse
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
     * @param  RequestInitiator  $client
     * @return JsonResponse
     */
    public function readItem(int $shipmentPackageId, int $basketItemId, Request $request, RequestInitiator $client): JsonResponse
    {
        //todo Проверка прав
        /** @var Model|RestSerializable $modelClass */
        $modelClass = $this->modelItemsClass();
        $restQuery = new RestQuery($request);
        $baseQuery = $modelClass::query()
            ->where('shipment_package_id', $shipmentPackageId)
            ->where('basket_item_id', $basketItemId);
        $query = $modelClass::modifyQuery($baseQuery, $restQuery);
        
        $items = $query->get()
            ->map(function (RestSerializable $model) use ($restQuery) {
                return $model->toRest($restQuery);
            });
        
        return response()->json([
            'items' => $items
        ]);
    }
    
    /**
     * Добавить/обновить/удалить элемент (собранный товар с одного склада одного мерчанта) коробки отправления
     * @param  int  $shipmentPackageId
     * @param  int  $basketItemId
     * @param  Request  $request
     * @return Response
     */
    public function setItem(int $shipmentPackageId, int $basketItemId, Request $request, RequestInitiator $client): Response
    {
        // todo добавить проверку прав
        $qty = (float) $request->input('qty');
        $setBy = (int) $request->input('set_by');
    
        $ok = true;
        /** @var Model|RestSerializable $modelClass */
        $modelClass = $this->modelItemsClass();
        $shipmentPackageItem = $modelClass::query()
            ->where('shipment_package_id', $shipmentPackageId)
            ->where('basket_item_id', $basketItemId)
            ->first();
        if (!$qty && !is_null($shipmentPackageItem)) {
            //Удаляем элемент из коробки отправления
            try {
                $ok = $shipmentPackageItem->delete();
            } catch (\Exception $e) {
                $ok = false;
                $message = $e->getMessage();
            }
        } else {
            /** @var BasketItem $basketItem */
            $basketItem = BasketItem::find($basketItemId);
            
            if (!$setBy) {
                $ok = false;
                $message = 'set_by is empty';
            } elseif ($basketItem->qty < $qty) {
                $ok = false;
                $message = 'shipment package qty can\'t be more than basket item qty';
            } else {
                if (is_null($shipmentPackageItem)) {
                    $shipmentPackageItem = new $modelClass();
                }
                $shipmentPackageItem->updateOrCreate([
                    'shipment_package_id' => $shipmentPackageId,
                    'basket_item_id' => $basketItemId,
                ], ['qty' => $qty, 'set_by' => $setBy]);
            }
        }
    
        if (!$ok) {
            throw new HttpException(500, $message);
        }
    
        return response('', 204);
    }
}
