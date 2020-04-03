<?php

namespace App\Http\Controllers\V1;

use App\Core\Order\OrderReader;
use App\Core\Order\OrderWriter;
use App\Http\Controllers\Controller;
use App\Models\Delivery\DeliveryType;
use App\Models\Order\Order;
use App\Models\Order\OrderComment;
use App\Models\Order\OrderStatus;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentStatus;
use App\Services\OrderService;
use Carbon\Carbon;
use Greensight\CommonMsa\Rest\RestQuery;
use Greensight\Logistics\Dto\Lists\DeliveryMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
     * Получить список заказов
     * @param  Request  $request
     * @return JsonResponse
     */
    public function read(Request $request): JsonResponse
    {
        $reader = new OrderReader();

        return response()->json([
            'items' => $reader->list(new RestQuery($request)),
        ]);
    }

    /**
     * Получить количество заказов по заданому фильтру
     * @param  Request  $request
     * @return JsonResponse
     */
    public function count(Request $request): JsonResponse
    {
        $reader = new OrderReader();

        return response()->json($reader->count(new RestQuery($request)));
    }

    /**
     * Задать список оплат заказа
     * @param  int  $id
     * @param  Request  $request
     * @return Response
     * @throws \Exception
     */
    public function setPayments(int $id, Request $request): Response
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
     * Обновить заказ
     * @param  int  $id
     * @param  Request  $request
     * @param  OrderService  $orderService
     * @return Response
     */
    public function update(int $id, Request $request, OrderService $orderService): Response
    {
        $order = $orderService->getOrder($id);
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

            'delivery_method' => ['nullable', Rule::in(array_keys(DeliveryMethod::allMethods()))],
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
     * Удалить заказ
     * @param  int  $id
     * @param  OrderService  $orderService
     * @return Response
     * @throws \Exception
     */
    public function delete(int $id, OrderService $orderService): Response
    {
        $order = $orderService->getOrder($id);
        if (!$order) {
            throw new NotFoundHttpException('order not found');
        }
        $ok = $order->delete();
        if (!$ok) {
            throw new HttpException(500, 'unable to save order');
        }

        return response('', 204);
    }

    /**
     * Отменить заказ
     * @param  int  $id
     * @param  OrderService  $orderService
     * @return Response
     * @throws \Exception
     */
    public function cancel(int $id, OrderService $orderService): Response
    {
        $order = $orderService->getOrder($id);
        if (!$order) {
            throw new NotFoundHttpException('order not found');
        }
        if (!$orderService->cancel($order)) {
            throw new HttpException(500);
        }

        return response('', 204);
    }


    /**
     * Добавить комментарий к заказу
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response
     */
    public function setComment(int $id, Request $request): Response
    {
        $data = $request->all();
        $validator = Validator::make($data, [
            'text' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            throw new BadRequestHttpException($validator->errors()->first());
        }

        $comment = OrderComment::query()->where(['order_id' => $id])->get()->first();
        if (!$comment) {
            $comment = new OrderComment();
        }

        $comment->order_id = $id;
        $comment->text = $data['text'];

        $ok = $comment->save();

        if (!$ok) {
            throw new HttpException(500, 'unable to save comment');
        }

        return response('', 204);
    }

    /**
     * @return JsonResponse
     */
    public function doneReferral(): JsonResponse
    {
        $data = $this->validate(request(), [
            'date_from' => 'nullable|integer',
            'date_to' => 'nullable|integer',
        ]);

        $builder = Order::query()->where('status', OrderStatus::DONE);

        if (isset($data['date_from'])) {
            $builder->where('status_at', '>=', Carbon::createFromTimestamp($data['date_from']));
        }

        if (isset($data['date_to'])) {
            $builder->where('status_at', '<', Carbon::createFromTimestamp($data['date_to']));
        }

        $orders = $builder->with(['basket.items'])->get();

        return response()->json([
            'items' => $orders->map(function (Order $order) {
                $items = [];
                foreach ($order->basket->items as $item) {
                    $items[] = [
                        'offer_id' => $item->offer_id,
                        'name' => $item->name,
                        'qty' => $item->qty,
                        'price' => $item->price,
                        'referrer_id' => $item->referrer_id,
                    ];
                }

                return [
                    'customer_id' => $order->customer_id,
                    'created_at' => $order->created_at->format('Y-m-d H:i:s'),
                    'number' => $order->number,
                    'items' => $items,
                ];
            }),
        ]);
    }
}

