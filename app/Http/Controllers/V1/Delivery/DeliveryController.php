<?php
namespace App\Http\Controllers\V1\Delivery;

use App\Http\Controllers\Controller;
use App\Models\Delivery\Delivery;
use App\Models\Order\Order;
use Greensight\CommonMsa\Rest\Controller\CountAction;
use Greensight\CommonMsa\Rest\Controller\DeleteAction;
use Greensight\CommonMsa\Rest\Controller\ReadAction;
use Greensight\CommonMsa\Rest\Controller\UpdateAction;
use Greensight\CommonMsa\Rest\Controller\Validation\RequiredOnPost;
use Greensight\CommonMsa\Rest\RestQuery;
use Greensight\CommonMsa\Rest\RestSerializable;
use Greensight\CommonMsa\Services\RequestInitiator\RequestInitiator;
use Greensight\Logistics\Dto\Lists\DeliveryMethod;
use Greensight\Logistics\Dto\Lists\DeliveryOrderStatus\DeliveryOrderStatus;
use Greensight\Logistics\Dto\Lists\DeliveryService;
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
 * Class DeliveryController
 * @package App\Http\Controllers\V1\Delivery
 */
class DeliveryController extends Controller
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
        return Delivery::class;
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
        return Delivery::FILLABLE;
    }
    
    /**
     * @return array
     */
    protected function inputValidators(): array
    {
        return [
            'status' => ['nullable', Rule::in(array_keys(DeliveryOrderStatus::allStatuses()))],
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
     * Подсчитать кол-во доставок
     * @param  Request  $request
     * @param  RequestInitiator  $client
     * @return \Illuminate\Http\JsonResponse
     */
    public function count(Request $request, RequestInitiator $client): JsonResponse
    {
        return $this->countTrait($request, $client);
    }
    
    /**
     * Подсчитать кол-во доставок заказа
     * @param int $orderId
     * @param  Request  $request
     * @param  RequestInitiator  $client
     * @return \Illuminate\Http\JsonResponse
     */
    public function countByDelivery(int $orderId, Request $request, RequestInitiator $client): JsonResponse
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
     * //todo swagger
     * @OA\Post(
     *     path="/api/v1/orders/{id}/delivery",
     *     tags={"delivery"},
     *     summary="Создать доставку",
     *     operationId="createDelivery",
     *     @OA\Parameter(description="ID заказа", in="path", name="id", required=true, @OA\Schema(type="integer")),
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
     * Список доставок / информация о доставке
     * @param  Request  $request
     * @param  RequestInitiator  $client
     * @return \Illuminate\Http\JsonResponse
     */
    public function read(Request $request, RequestInitiator $client): JsonResponse
    {
        return $this->readTrait($request, $client);
    }
    
    /**
     * Список доставок заказа
     * @param  int  $orderId
     * @param  Request  $request
     * @param  RequestInitiator  $client
     * @return JsonResponse
     */
    public function readByOrder(int $orderId, Request $request, RequestInitiator $client): JsonResponse
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
     * Изменить доставку
     * @param  int  $id
     * @param  Request  $request
     * @return \Illuminate\Contracts\Routing\ResponseFactory|Response
     * //todo swagger
     * @OA\Put(
     *     path="/api/v1/delivery/{id}",
     *     tags={"delivery"},
     *     summary="Изменить доставку",
     *     operationId="updateDelivery",
     *     @OA\Parameter(description="ID доставки", in="path", name="id", required=true, @OA\Schema(type="integer")),
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
     * Удалить доставку
     * @param  int  $id
     * @return \Illuminate\Contracts\Routing\ResponseFactory|Response
     * @throws \Exception
     *
     * @OA\Delete(
     *     path="/api/v1/delivery/{id}",
     *     tags={"delivery"},
     *     summary="Удалить доставку",
     *     operationId="deleteDelivery",
     *     @OA\Parameter(description="ID доставки", in="path", name="id", required=true, @OA\Schema(type="integer")),
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
}
