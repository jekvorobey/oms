<?php

namespace App\Http\Controllers\V1\Delivery;

use App\Http\Controllers\Controller;
use App\Models\Delivery\Cargo;
use App\Models\Delivery\CargoStatus;
use App\Models\Delivery\Delivery;
use App\Models\Delivery\Shipment;
use App\Models\Delivery\ShipmentItem;
use App\Models\Delivery\ShipmentStatus;
use App\Services\DeliveryService;
use Greensight\CommonMsa\Dto\FileDto;
use Greensight\CommonMsa\Rest\Controller\CountAction;
use Greensight\CommonMsa\Rest\Controller\DeleteAction;
use Greensight\CommonMsa\Rest\Controller\ReadAction;
use Greensight\CommonMsa\Rest\Controller\UpdateAction;
use Greensight\CommonMsa\Rest\Controller\Validation\RequiredOnPost;
use Greensight\CommonMsa\Rest\RestQuery;
use Greensight\CommonMsa\Rest\RestSerializable;
use Greensight\CommonMsa\Services\FileService\FileService;
use Greensight\CommonMsa\Services\RequestInitiator\RequestInitiator;
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
    use CountAction {
        count as countTrait;
    }
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
        return Shipment::class;
    }
    
    /**
     * Получить класс модели элементов в виде строки
     * Пример: return MyModel::class;
     * @return string
     */
    public function modelItemsClass(): string
    {
        return ShipmentItem::class;
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
        return Shipment::FILLABLE;
    }
    
    /**
     * @return array
     */
    protected function inputValidators(): array
    {
        return [
            'delivery_id' => [new RequiredOnPost(), 'integer'],
            'merchant_id' => [new RequiredOnPost(), 'integer'],
            'store_id' => [new RequiredOnPost(), 'integer'],
            'cargo_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(ShipmentStatus::validValues())],
            'number' => [new RequiredOnPost(), 'string'],
            'required_shipping_at' => [new RequiredOnPost(), 'date'],
        ];
    }
    
    /**
     * Подсчитать кол-во отправлений
     * @param  Request  $request
     * @param  RequestInitiator  $client
     * @return \Illuminate\Http\JsonResponse
     */
    public function count(Request $request, RequestInitiator $client): JsonResponse
    {
        return $this->countTrait($request, $client);
    }
    
    /**
     * Подсчитать кол-во отправлений доставки
     * @param int $deliveryId
     * @param  Request  $request
     * @param  RequestInitiator  $client
     * @return \Illuminate\Http\JsonResponse
     */
    public function countByDelivery(int $deliveryId, Request $request, RequestInitiator $client): JsonResponse
    {
        //todo Проверка прав
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
     * Создать отправление
     * @param  int  $deliveryId
     * @param  Request  $request
     * @return JsonResponse
     * //todo swagger
     * @OA\Post(
     *     path="/api/v1/delivery/{id}/shipments",
     *     tags={"shipment"},
     *     summary="Создать отправление",
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
    public function create(int $deliveryId, Request $request): JsonResponse
    {
        //todo Проверка прав
        /** @var Delivery $delivery */
        $delivery = Delivery::find($deliveryId);
        if (!$delivery) {
            throw new NotFoundHttpException('delivery not found');
        }
        
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
            'id' => $shipment->id
        ], 201);
    }
    
    /**
     * Список отправлений / информация об отправлении
     * @param  Request  $request
     * @param  RequestInitiator  $client
     * @return \Illuminate\Http\JsonResponse
     */
    public function read(Request $request, RequestInitiator $client): JsonResponse
    {
        return $this->readTrait($request, $client);
    }
    
    /**
     * Получить собранные неотгруженные отправления со схожими параметрами для текущего груза (склад, служба доставки)
     * @param  Request  $request
     * @param  RequestInitiator  $client
     * @return JsonResponse
     */
    public function similarUnshippedShipments(Request $request, RequestInitiator $client): JsonResponse
    {
        //todo Проверка прав
        $validatedData = $request->validate([
            'cargo_id' => 'integer|required',
        ]);
        
        $cargo = Cargo::find($validatedData['cargo_id']);
        $similarCargosIds = Cargo::query()
            ->select('id')
            ->where('id', '!=', $cargo->id)
            ->where('merchant_id', $cargo->merchant_id)
            ->where('store_id', $cargo->store_id)
            ->whereIn('status', [CargoStatus::STATUS_CREATED, CargoStatus::STATUS_REQUEST_SEND])
            ->where('delivery_service', $cargo->delivery_service)
            ->pluck('id')
            ->all();
    
        $shipments = Shipment::query()
            ->where('merchant_id', $cargo->merchant_id)
            ->where('store_id', $cargo->store_id)
            ->where(function(Builder $q) use ($similarCargosIds) {
                $q->whereNull('cargo_id')
                    ->orWhereIn('cargo_id', $similarCargosIds);
            })
            ->where('status', ShipmentStatus::STATUS_ASSEMBLED)
            ->whereHas('delivery', function(Builder $q) use ($cargo){
                $q->where('delivery_service', $cargo->delivery_service);
            })
            ->get();
        
        return response()->json([
            'items' => $shipments
        ]);
    }
    
    /**
     * Список отправлений доставки
     * @param  int  $deliveryId
     * @param  Request  $request
     * @param  RequestInitiator  $client
     * @return JsonResponse
     */
    public function readByDelivery(int $deliveryId, Request $request, RequestInitiator $client): JsonResponse
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
        $query = $modelClass::modifyQuery($baseQuery->where('delivery_id', $deliveryId), $restQuery);
        
        $items = $query->get()
            ->map(function (RestSerializable $model) use ($restQuery) {
                return $model->toRest($restQuery);
            });
        
        return response()->json([
            'items' => $items
        ]);
    }
    
    /**
     * Изменить отправление
     * @param  int  $id
     * @param  Request  $request
     * @return \Illuminate\Contracts\Routing\ResponseFactory|Response
     * //todo swagger
     * @OA\Put(
     *     path="/api/v1/shipments/{id}",
     *     tags={"shipment"},
     *     summary="Изменить отправление",
     *     operationId="updateShipment",
     *     @OA\Parameter(description="ID отправления", in="path", name="id", required=true, @OA\Schema(type="integer")),
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
     * Удалить отправление
     * @param  int  $id
     * @return \Illuminate\Contracts\Routing\ResponseFactory|Response
     * @throws \Exception
     *
     * @OA\Delete(
     *     path="/api/v1/shipments/{id}",
     *     tags={"shipment"},
     *     summary="Удалить отправление",
     *     operationId="deleteShipment",
     *     @OA\Parameter(description="ID отправления", in="path", name="id", required=true, @OA\Schema(type="integer")),
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
     * Подсчитать кол-во элементов (товаров с одного склада одного мерчанта) отправления
     * @param  int  $shipmentId
     * @param  Request  $request
     * @param  RequestInitiator  $client
     * @return JsonResponse
     */
    public function countItems(int $shipmentId, Request $request, RequestInitiator $client): JsonResponse
    {
        //todo Проверка прав
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
     * Список элементов (товаров с одного склада одного мерчанта) отправления
     * @param  int  $shipmentId
     * @param  Request  $request
     * @param  RequestInitiator  $client
     * @return JsonResponse
     */
    public function readItems(int $shipmentId, Request $request, RequestInitiator $client): JsonResponse
    {
        //todo Проверка прав
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
            'items' => $items
        ]);
    }
    
    /**
     * Информация об элементе (товар с одного склада одного мерчанта) отправления
     * @param  int  $shipmentId
     * @param  int  $basketItemId
     * @param  Request  $request
     * @param  RequestInitiator  $client
     * @return JsonResponse
     */
    public function readItem(int $shipmentId, int $basketItemId, Request $request, RequestInitiator $client): JsonResponse
    {
        /** @var Model|RestSerializable $modelClass */
        $modelClass = $this->modelItemsClass();
        $restQuery = new RestQuery($request);
        $baseQuery = $modelClass::query()
            ->where('shipment_id', $shipmentId)
            ->where('basket_item_id', $basketItemId);
        $query = $modelClass::modifyQuery($baseQuery, $restQuery);
    
        //todo Проверка прав
    
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
     * Создать элемент (товар с одного склада одного мерчанта) отправления
     * @param  int  $shipmentId
     * @param  int  $basketItemId
     * @param  Request  $request
     * @return Response
     */
    public function createItem(int $shipmentId, int $basketItemId, RequestInitiator $client): Response
    {
        /** @var Shipment $shipment */
        $shipment = Shipment::find($shipmentId);
        if (!$shipment) {
            throw new NotFoundHttpException('shipment not found');
        }
    
        //todo Проверка прав
    
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
     * Удалить элемент (товар с одного склада одного мерчанта) отправления
     * @param  int  $shipmentId
     * @param  int  $basketItemId
     * @param  RequestInitiator  $client
     * @return Response
     */
    public function deleteItem(int $shipmentId, int $basketItemId, RequestInitiator $client): Response
    {
        /** @var ShipmentItem $shipmentItem */
        $shipmentItem = ShipmentItem::query()
            ->where('shipment_id', $shipmentId)
            ->where('basket_item_id', $basketItemId)
            ->first();
        if (!$shipmentItem) {
            throw new NotFoundHttpException('shipment item not found');
        }
        
        //todo Проверка прав
        
        try {
            $ok = $shipmentItem->delete();
        } catch (\Exception $e) {
            $ok = false;
        }
    
        if (!$ok) {
            throw new HttpException(500);
        }
        
        return response('', 204);
    }

    /**
     * @param  int  $shipmentId
     * @param  DeliveryService  $deliveryService
     * @param  FileService  $fileService
     * @return JsonResponse
     */
    public function barcodes(int $shipmentId, DeliveryService $deliveryService, FileService $fileService): JsonResponse
    {
        $deliveryOrderBarcodesDto = $deliveryService->getShipmentBarcodes($shipmentId);

        if ($deliveryOrderBarcodesDto) {
            if ($deliveryOrderBarcodesDto->success && $deliveryOrderBarcodesDto->file_id) {
                /** @var FileDto $fileDto */
                $fileDto = $fileService->getFiles([$deliveryOrderBarcodesDto->file_id])->first();

                return response()->json([
                    'absolute_url' => $fileDto->absoluteUrl(),
                    'original_name' => $fileDto->original_name,
                    'size' => $fileDto->size,
                ]);
            } else {
                throw new HttpException(500, $deliveryOrderBarcodesDto->message);
            }
        }

        throw new HttpException(500);
    }
}
