<?php
namespace App\Http\Controllers\V1\Delivery;

use App\Http\Controllers\Controller;
use App\Models\Delivery\Delivery;
use App\Models\Delivery\DeliveryMethod;
use App\Models\Delivery\DeliveryService;
use App\Models\Delivery\DeliveryStatus;
use App\Models\Order\Order;
use Greensight\CommonMsa\Rest\Controller\CountAction;
use Greensight\CommonMsa\Rest\Controller\DeleteAction;
use Greensight\CommonMsa\Rest\Controller\ReadAction;
use Greensight\CommonMsa\Rest\Controller\UpdateAction;
use Greensight\CommonMsa\Rest\Controller\Validation\RequiredOnPost;
use Greensight\CommonMsa\Services\RequestInitiator\RequestInitiator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;


/**
 * Class DeliveryController
 * @package App\Http\Controllers\V1
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
            'status' => ['nullable', Rule::in(DeliveryStatus::validValues())],
            'delivery_method' => [new RequiredOnPost(), Rule::in(DeliveryMethod::validValues())],
            'delivery_service' => [new RequiredOnPost(), Rule::in(DeliveryService::validValues())],
            'xml_id' => ['nullable', 'string'],
            'number' => [new RequiredOnPost(), 'string'],
            'width' => [new RequiredOnPost(), 'numeric'],
            'height' => [new RequiredOnPost(), 'numeric'],
            'length' => [new RequiredOnPost(), 'numeric'],
            'weight' => [new RequiredOnPost(), 'numeric'],
            'delivery_at' => [new RequiredOnPost(), 'date'],
        ];
    }
    
    /**
     * todo заменить на настоящие данные
     *
     * @OA\Get(
     *     path="/api/v1/delivery",
     *     tags={"delivery"},
     *     summary="Непонятно что про доставку",
     *     operationId="deliveryInfo",
     *     @OA\Response(
     *         response=200,
     *         description="OK"
     *     ),
     * )
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function info(Request $request)
    {
        // $request->basket
        // $request->deliveryAddress
        // $request->deliveryMethod (1 - самовывоз, 2 - доставка)
        $data =  [
            'cost' => (float) rand(100, 999),
            'dateFrom' => Carbon::now()->addDays(1)->toDateTimeString(),
            'dateTo' => Carbon::now()->addDays(rand(1,10))->toDateTimeString(),
            'parcels' => [
                [
                    'date' => Carbon::now()->addDays(rand(1,10))->toDateTimeString(),
                    'timeFrom' => '10:00',
                    'timeTo' => '15:00'
                ],
                [
                    'date' => Carbon::now()->addDays(rand(1,10))->toDateTimeString(),
                    'timeFrom' => '10:00',
                    'timeTo' => '15:00'
                ]
            ]

        ];

        return response()->json($data, 200);
    }

    /**
     * todo заменить на настоящие данные
     *
     *  @OA\Get(
     *     path="/api/v1/delivery/pvz",
     *     tags={"delivery"},
     *     summary="Непонятно что про пункты самовывоза",
     *     operationId="deliveryPvz",
     *     @OA\Response(
     *         response=200,
     *         description="OK"
     *     ),
     * )
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function infoPvz(Request $request)
    {
        $data =  [
            'cost' => (float) rand(100, 999),
            'dateFrom' => Carbon::now()->addDays(1)->toDateTimeString(),
            'dateTo' => Carbon::now()->addDays(rand(1,10))->toDateTimeString(),
            'address' => 'Одинцово, ул Русаков, 124'

        ];

        return response()->json($data, 200);
    }
    
    /**
     * Подситать кол-во доставок
     * @param  Request  $request
     * @param  RequestInitiator  $client
     * @return \Illuminate\Http\JsonResponse
     */
    public function count(Request $request, RequestInitiator $client): JsonResponse
    {
        return $this->countTrait($request, $client);
    }
    
    /**
     * Создать доставку
     * @param  int  $orderId
     * @param  Request  $request
     * @return JsonResponse
     *
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
     *             //todo swagger
     *         )
     *     ),
     *     @OA\Response(
     *         response=204,
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
     * Информация о доставке
     * @param  Request  $request
     * @param  RequestInitiator  $client
     * @return \Illuminate\Http\JsonResponse
     */
    public function read(Request $request, RequestInitiator $client)
    {
        return $this->readTrait($request, $client);
    }
    
    /**
     * Изменить доставку
     * @param  int  $id
     * @param  Request  $request
     * @return \Illuminate\Contracts\Routing\ResponseFactory|Response
     *
     * @OA\Put(
     *     path="/api/v1/delivery/{id}",
     *     tags={"delivery"},
     *     summary="Изменить доставку",
     *     operationId="createDelivery",
     *     @OA\Parameter(description="ID доставки", in="path", name="id", required=true, @OA\Schema(type="integer")),
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
    public function edit(int $id, Request $request, RequestInitiator $client): Response
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
