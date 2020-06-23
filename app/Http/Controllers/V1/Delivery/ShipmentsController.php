<?php

namespace App\Http\Controllers\V1\Delivery;

use App\Http\Controllers\Controller;
use App\Models\Delivery\Cargo;
use App\Models\Delivery\CargoStatus;
use App\Models\Delivery\Delivery;
use App\Models\Delivery\Shipment;
use App\Models\Delivery\ShipmentExport;
use App\Models\Delivery\ShipmentItem;
use App\Models\Delivery\ShipmentStatus;
use App\Models\Payment\PaymentStatus;
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
    use CountAction;
    use ReadAction;
    use UpdateAction;
    use DeleteAction;

    /**
     * @inheritDoc
     */
    public function modelClass(): string
    {
        return Shipment::class;
    }

    /**
     * @inheritDoc
     */
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
     * Получить ID и сумму (руб.) принятых мерчантом заказов за период
     * @return JsonResponse
     */
    public function getActiveIds()
    {
        $data = $this->validate(request(), [
            'merchant_id' => 'required|integer',
            'period' => 'required|string'
        ]);
        $orders = Shipment::query()
            ->select(['merchant_id', 'cost'])
            ->where([
                ['created_at', '>', $data['period']],
                ['merchant_id', '=', $data['merchant_id']],
                ['status', '>=', ShipmentStatus::AWAITING_CONFIRMATION],
                ['status', '<=', ShipmentStatus::DONE],
                ['is_canceled', '=', 0]
            ]);
        if (!$orders)
        {
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
     * Получить ID и сумму (руб.) доставленных заказов у мерчанта за период
     * @return JsonResponse
     */
    public function getDeliveredIds()
    {
        $data = $this->validate(request(), [
            'merchant_id' => 'required|integer',
            'period' => 'required|string'
        ]);
        $orders = Shipment::query()
            ->select(['merchant_id', 'cost'])
            ->where([
                ['created_at', '>', $data['period']],
                ['merchant_id', '=', $data['merchant_id']],
                ['status', '=', ShipmentStatus::DONE],
            ]);
        if (!$orders)
        {
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
     * Подсчитать кол-во отправлений доставки
     * @param int $deliveryId
     * @param  Request  $request
     * @return \Illuminate\Http\JsonResponse
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
     * Создать отправление
     * @param  int  $deliveryId
     * @param  Request  $request
     * @return JsonResponse
     */
    public function create(int $deliveryId, Request $request): JsonResponse
    {
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
     * Получить собранные неотгруженные отправления со схожими параметрами для текущего груза (склад, служба доставки)
     * @param  Request  $request
     * @return JsonResponse
     */
    public function similarUnshippedShipments(Request $request): JsonResponse
    {
        $validatedData = $this->validate($request, [
            'cargo_id' => 'integer|required',
        ]);

        $cargo = Cargo::find($validatedData['cargo_id']);
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
            ->where(function(Builder $q) use ($similarCargosIds) {
                $q->whereNull('cargo_id')
                    ->orWhereIn('cargo_id', $similarCargosIds);
            })
            ->where('status', ShipmentStatus::ASSEMBLED)
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
     * @return JsonResponse
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
            'items' => $items
        ]);
    }

    /**
     * Подсчитать кол-во элементов (товаров с одного склада одного мерчанта) отправления
     * @param  int  $shipmentId
     * @param  Request  $request
     * @return JsonResponse
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
     * Список элементов (товаров с одного склада одного мерчанта) отправления
     * @param  int  $shipmentId
     * @param  Request  $request
     * @return JsonResponse
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
            'items' => $items
        ]);
    }

    /**
     * Информация об элементе (товар с одного склада одного мерчанта) отправления
     * @param  int  $shipmentId
     * @param  int  $basketItemId
     * @param  Request  $request
     * @return JsonResponse
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
     * Создать элемент (товар с одного склада одного мерчанта) отправления
     * @param  int  $shipmentId
     * @param  int  $basketItemId
     * @return Response
     */
    public function createItem(int $shipmentId, int $basketItemId): Response
    {
        /** @var Shipment $shipment */
        $shipment = Shipment::find($shipmentId);
        if (!$shipment) {
            throw new NotFoundHttpException('shipment not found');
        }

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
     * @return Response
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
        } catch (\Exception $e) {
            $ok = false;
        }

        if (!$ok) {
            throw new HttpException(500);
        }

        return response('', 204);
    }

    /**
     * Получить штрихкоды для мест (коробок) отправления
     * @param  int  $id
     * @param  DeliveryService  $deliveryService
     * @param  FileService  $fileService
     * @return JsonResponse
     */
    public function barcodes(int $id, DeliveryService $deliveryService, FileService $fileService): JsonResponse
    {
        $shipment = $deliveryService->getShipment($id);
        if (!$shipment) {
            throw new NotFoundHttpException('shipment not found');
        }
        $deliveryOrderBarcodesDto = $deliveryService->getShipmentBarcodes($shipment);

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

    /**
     * Получить квитанцию cdek для заказа на доставку
     * @param  int  $id
     * @param  DeliveryService  $deliveryService
     * @param  FileService  $fileService
     * @return JsonResponse
     */
    public function cdekReceipt(int $id, DeliveryService $deliveryService, FileService $fileService): JsonResponse
    {
        $shipment = $deliveryService->getShipment($id);
        if (!$shipment) {
            throw new NotFoundHttpException('shipment not found');
        }
        $cdekDeliveryOrderReceiptDto = $deliveryService->getShipmentCdekReceipt($shipment);

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
     * Пометить как проблемное
     * @param  int  $id
     * @param Request $request
     * @param  DeliveryService  $deliveryService
     * @return Response
     */
    public function markAsProblem(int $id, Request $request, DeliveryService $deliveryService): Response
    {
        $shipment = $deliveryService->getShipment($id);
        if (!$shipment) {
            throw new NotFoundHttpException('shipment not found');
        }
        $data = $this->validate($request, [
            'assembly_problem_comment' => ['required'],
        ]);

        if (!$deliveryService->markAsProblemShipment($shipment, $data['assembly_problem_comment'])) {
            throw new HttpException(500);
        }

        return response('', 204);
    }

    /**
     * Пометить как непроблемное
     * @param  int  $id
     * @param  DeliveryService  $deliveryService
     * @return Response
     */
    public function markAsNonProblem(int $id, DeliveryService $deliveryService): Response
    {
        $shipment = $deliveryService->getShipment($id);
        if (!$shipment) {
            throw new NotFoundHttpException('shipment not found');
        }
        if (!$deliveryService->markAsNonProblemShipment($shipment)) {
            throw new HttpException(500);
        }

        return response('', 204);
    }

    /**
     * Отменить отправление
     * @param  int  $id
     * @param  DeliveryService  $deliveryService
     * @return Response
     * @throws \Exception
     */
    public function cancel(int $id, DeliveryService $deliveryService): Response
    {
        $shipment = $deliveryService->getShipment($id);
        if (!$shipment) {
            throw new NotFoundHttpException('shipment not found');
        }
        if (!$deliveryService->cancelShipment($shipment)) {
            throw new HttpException(500);
        }

        return response('', 204);
    }

    public function readNew(Request $request): JsonResponse
    {
        $data = $this->validate($request, [
            'merchantId' => 'required|int',
        ]);

        $merchantId = $data['merchantId'];

        $items = $this->modelClass()::with(['basketItems', 'delivery.order'])
            ->where('merchant_id', $merchantId)
            ->where('status', '>=', ShipmentStatus::ASSEMBLING)
            ->whereIn('payment_status', [PaymentStatus::HOLD, PaymentStatus::PAID])
            ->where(function($query) {
                return $query->doesntHave('export')
                    ->orWhereHas('export', function ($subquery) {
                        return $subquery->whereNull('shipment_xml_id')->where('err_code', '!=', 500);
                    });
            })
            ->get();

        return response()->json([
            'items' => $items
        ]);
    }

    public function createShipmentExport(Request $request): JsonResponse
    {
        $data = $this->validate($request, [
            'shipment_id' => 'required|int',
            'merchant_integration_id' => 'required|int',
            'shipment_xml_id' => 'nullable|string',
            'err_code' => 'nullable|int',
            'err_message' => 'nullable|string'
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
            'id' => $shipmentExport->id
        ], 201);
    }
}
