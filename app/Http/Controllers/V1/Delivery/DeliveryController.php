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
    use CountAction;
    use ReadAction;
    use UpdateAction;
    use DeleteAction;
    
    /**
     * @inheritDoc
     */
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
        ];
    }
    
    /**
     * Подсчитать кол-во доставок заказа
     * @param int $orderId
     * @param  Request  $request
     * @return \Illuminate\Http\JsonResponse
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
     * Создать доставку
     * @param  int  $orderId
     * @param  Request  $request
     * @return JsonResponse
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
            'id' => $delivery->id
        ], 201);
    }
    
    /**
     * Список доставок заказа
     * @param  int  $orderId
     * @param  Request  $request
     * @return JsonResponse
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
            'items' => $items
        ]);
    }

    /**
     * Отменить доставку
     * @param  int  $id
     * @param  OmsDeliveryService  $deliveryService
     * @return Response
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
     * Создать/обновить заказ на доставку у службы доставки
     * @param  int  $id
     * @param  OmsDeliveryService  $deliveryService
     * @return Response
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
     * Отменить заказ на доставку у службы доставки
     * @param  int  $id
     * @param  OmsDeliveryService  $deliveryService
     * @return Response
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
     * @return JsonResponse
     */
    public function countTodayByDeliveryServices(): JsonResponse
    {
        $deliveries = Delivery::query()
            ->select('delivery_service', DB::raw('count(*) as total'))
            ->whereDate('created_at', now()->setTime(0, 0))
            ->groupBy(['delivery_service', 'created_at'])
            ->get();

        return response()->json($deliveries->map(function (Delivery $delivery) {
            return [
                'delivery_service_id' => $delivery->delivery_service,
                'qty_today' => $delivery['total'],
            ];
        }));
    }
}
