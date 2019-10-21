<?php

namespace App\Http\Controllers\V1;

use App\Core\Order\OrderReader;
use App\Core\Order\OrderWriter;
use App\Http\Controllers\Controller;
use App\Models\Delivery\DeliveryMethod;
use App\Models\Delivery\DeliveryService;
use App\Models\Delivery\DeliveryType;
use App\Models\Order\Order;
use App\Models\Order\OrderStatus;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentStatus;
use Greensight\CommonMsa\Rest\RestQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class OrdersController
 * @package App\Http\Controllers\V1
 */
class OrdersController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/orders",
     *     tags={"order"},
     *     summary="Получить список заказов",
     *     operationId="listOrders",
     *     @OA\Response(
     *         response=200,
     *         description="OK",
     *         @OA\JsonContent(
     *             @OA\Property(property="items",type="array", @OA\Items(
     *                  ref="#/components/schemas/OrderItem"
     *             )),
     *         )
     *     ),
     * )
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function read(Request $request)
    {
        $reader = new OrderReader();
        return response()->json([
            'items' => $reader->list(new RestQuery($request)),
        ]);
    }
    
    /**
     * @OA\Get(
     *     path="/api/v1/orders/count",
     *     tags={"order"},
     *     summary="Получить количество заказов по заданому фильтру",
     *     operationId="countOrders",
     *     @OA\Response(
     *         response=200,
     *         description="OK",
     *         @OA\JsonContent(ref="#/components/schemas/CountResult")
     *     ),
     * )
     * @todo уточнить типы в swagger
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function count(Request $request)
    {
        $reader = new OrderReader();
        return response()->json($reader->count(new RestQuery($request)));
    }
    
    /**
     * @OA\Put(
     *     path="/api/v1/orders/{id}/payments",
     *     tags={"payment"},
     *     summary="Задать список оплат заказа",
     *     operationId="setPayments",
     *     @OA\Parameter(description="ID заказа",in="path",name="id",required=true,@OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="OK",
     *     ),
     * )
     *
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     * @todo уточнить типы в swagger
     */
    public function setPayments(int $id, Request $request)
    {
        $reader = new OrderReader();
        $writer = new OrderWriter();

        $order = $reader->byId($id);
        if (!$order) {
            throw new NotFoundHttpException('order not found');
        }
        /** @var \Illuminate\Validation\Validator $validator */
        $data = $request->all();
        $validator = Validator::make($data, [
            'payments' => 'required|array',
            'payments.*.id' => 'nullable|integer',
            'payments.*.sum' => 'nullable|numeric',
            'payments.*.status' => 'nullable|integer',
            'payments.*.type' => 'nullable|integer',
            'payments.*.payment_system'=> 'nullable|integer',
            'payments.*.data' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            throw new BadRequestHttpException($validator->errors()->first());
        }
        $payments = collect();
        foreach ($data['payments'] as $rawPayment) {
            $payments[] = new Payment($rawPayment);
        }
        $writer->setPayments($order, $payments);
        return response('', 204);
    }
    
    /**
     * @OA\Post(
     *     path="/api/v1/orders",
     *     tags={"order"},
     *     summary="Создать заказ",
     *     operationId="createOrder",
     *     @OA\Response(
     *         response=200,
     *         description="OK",
     *         @OA\JsonContent(ref="#/components/schemas/CreateResult"),
     *     ),
     * )
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     * @todo уточнить типы в swagger
     */
    public function create(Request $request)
    {
        // todo Добавить провеку прав
        $data = $request->all();
        $validator = Validator::make($data, [
            'basket_id' => 'required|integer',
            'customer_id' => 'required|integer',
            'cost' => 'required|numeric',

            'status' => ['nullable', Rule::in(OrderStatus::validValues())],
            'payment_status' => ['nullable', Rule::in(PaymentStatus::validValues())],
            
            'delivery_service' => ['nullable', Rule::in(DeliveryService::validValues())],
            'delivery_method' => ['nullable', Rule::in(DeliveryMethod::validValues())],
            'delivery_type' => ['nullable', Rule::in(DeliveryType::validValues())],
            'delivery_comment' => ['nullable', 'string'],
            'delivery_address' => ['nullable', 'array'],
            'receiver_name' => ['nullable', 'string'],
            'receiver_phone' => ['nullable', 'string'],
            'receiver_email' => ['nullable', 'string', 'email'],

            'manager_comment' => ['nullable', 'string'],
        ]);
        if ($validator->fails()) {
            throw new BadRequestHttpException($validator->errors()->first());
        }
        $writer = new OrderWriter();
        $id = $writer->create($data['customer_id'], $data['cost']);
        if (!$id) {
            throw new HttpException(500, 'unable to save order');
        }
        return response()->json([
            'id' => $id
        ]);
    }
    
    /**
     * @OA\Put(
     *     path="/api/v1/orders/{id}",
     *     tags={"order"},
     *     summary="Обновить заказ",
     *     operationId="updateOrder",
     *     @OA\Parameter(description="ID заказа",in="path",name="id",required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=204,
     *         description="OK"
     *     ),
     * )
     *
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     * @todo уточнить типы в swagger
     */
    public function update(int $id, Request $request)
    {
        // todo Добавить провеку прав
        /** @var Order $order */
        $order = Order::find($id);
        if (!$order) {
            throw new NotFoundHttpException('order not found');
        }
        $data = $request->all();
        $validator = Validator::make($data, [
            'basket_id' => 'nullable|integer',
            'customer_id' => 'nullable|integer',
            'cost' => 'nullable|numeric',
            
            'status' => ['nullable', Rule::in(OrderStatus::validValues())],
            'payment_status' => ['nullable', Rule::in(PaymentStatus::validValues())],

            'delivery_service' => ['nullable', Rule::in(DeliveryService::validValues())],
            'delivery_method' => ['nullable', Rule::in(DeliveryMethod::validValues())],
            'delivery_type' => ['nullable', Rule::in(DeliveryType::validValues())],
            'delivery_comment' => ['nullable', 'string'],
            'delivery_address' => ['nullable', 'array'],
            'receiver_name' => ['nullable', 'string'],
            'receiver_phone' => ['nullable', 'string'],
            'receiver_email' => ['nullable', 'string', 'email'],
            
            'manager_comment' => ['nullable', 'string'],
        ]);
        if ($validator->fails()) {
            throw new BadRequestHttpException($validator->errors()->first());
        }
        $order->fill($data);
        $ok = $order->save();
        if (!$ok) {
            throw new HttpException(500, 'unable to save order');
        }
        
        return response('', 204);
    }
    
    /**
     * @OA\Delete(
     *     path="/api/v1/orders/{id}",
     *     tags={"order"},
     *     summary="Удалить заказ",
     *     operationId="deleteOrder",
     *     @OA\Parameter(description="ID заказа",in="path",name="id",required=true,@OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=204,
     *         description="OK"
     *     ),
     * )
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     * @todo уточнить типы в swagger
     */
    public function delete(int $id)
    {
        $order = Order::find($id);
        if (!$order) {
            throw new NotFoundHttpException('order not found');
        }
        $ok = $order->delete();
        if (!$ok) {
            throw new HttpException(500, 'unable to save order');
        }
        return response('', 204);
    }
}
