<?php

namespace App\Http\Controllers\V1;

use App\Core\Order\OrderReader;
use App\Core\Order\OrderWriter;
use App\Http\Controllers\Controller;
use App\Models\Basket\BasketItem;
use App\Models\Delivery\DeliveryType;
use App\Models\Delivery\Shipment;
use App\Models\Delivery\ShipmentItem;
use App\Models\Delivery\ShipmentStatus;
use App\Models\Order\Order;
use App\Models\Order\OrderComment;
use App\Models\Order\OrderConfirmationType;
use App\Models\Order\OrderStatus;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentStatus;
use App\Services\OrderService;
use Carbon\Carbon;
use Greensight\CommonMsa\Rest\RestQuery;
use Illuminate\Database\Eloquent\Collection;
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

    public function getByOffers(Request $request): JsonResponse
    {
        $data = $request->validate([
            'offersIds' => 'array|required',
            'perPage' => 'integer',
            'page' => 'integer',
        ]);

        $offersIds = $data['offersIds'];
        $perPage = $data['perPage'] ?? 5;
        $page = $data['page'] ?? 1;
        $offset = ($page-1) * $perPage;
        $basketIds = BasketItem::whereIn('offer_id', $offersIds)->select('basket_id');
        $basketItemsIds = BasketItem::whereIn('offer_id', $offersIds)->select('id');
        $shipmentIds = ShipmentItem::whereIn('basket_item_id', $basketItemsIds)->select('shipment_id');
        $deliveryIds = Shipment::whereIn('id', $shipmentIds)->select('delivery_id');
        $orders = Order::whereIn('basket_id', $basketIds)
            ->offset($offset)
            ->limit($perPage)
            ->with(
            ['deliveries' => function($q) use ($deliveryIds)
            {
                $q->whereIn('id', $deliveryIds);
            }
            ])
            ->has('deliveries')
            ->get();

        return response()->json($orders, 200);
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

            'delivery_type' => ['nullable', Rule::in(DeliveryType::validValues())],
            'delivery_address' => ['nullable', 'array'],
            'receiver_name' => ['nullable', 'string'],
            'receiver_phone' => ['nullable', 'string'],
            'receiver_email' => ['nullable', 'string', 'email'],

            'manager_comment' => ['nullable', 'string'],
            'confirmation_type' => ['nullable', Rule::in(OrderConfirmationType::validValues())],
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
     * Вручную оплатить заказ
     * Примечание: оплата по заказам автоматически должна поступать от платежной системы!
     * @param  int  $id
     * @param  OrderService  $orderService
     * @return Response
     * @throws \Exception
     */
    public function pay(int $id, OrderService $orderService): Response
    {
        $order = $orderService->getOrder($id);
        if (!$order) {
            throw new NotFoundHttpException('order not found');
        }
        if (!$orderService->pay($order)) {
            throw new HttpException(500);
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

        $orders = $builder->with(['basket.items', 'discounts', 'promoCodes'])->get();

        return response()->json([
            'items' => $orders->map(function (Order $order) {
                $items = [];
                foreach ($order->basket->items as $item) {
                    $items[] = [
                        'offer_id' => $item->offer_id,
                        'name' => $item->name,
                        'qty' => $item->qty,
                        'price' => $item->price,
                        'cost' => $item->cost,
                        'referrer_id' => $item->referrer_id,
                    ];
                }
                $promoCodes = [];
                foreach ($order->promoCodes as $promoCode) {
                    $promoCodes[] = [
                        'type' => $promoCode->type,
                        'discount_id' => $promoCode->discount_id,
                        'owner_id' => $promoCode->owner_id,
                        'code' => $promoCode->code,
                    ];
                }

                $discounts = [];
                foreach($order->discounts as $discount) {
                    $discounts[] = [
                        'discount_id' => $discount->discount_id,
                        'type' => $discount->type,
                        'promo_code_only' => $discount->promo_code_only,
                        'visible_in_catalog' => $discount->visible_in_catalog,
                        'items' => $discount->items,
                    ];
                }

                return [
                    'customer_id' => $order->customer_id,
                    'created_at' => $order->created_at->format('Y-m-d H:i:s'),
                    'number' => $order->number,
                    'promo_codes' => $promoCodes,
                    'discounts' => $discounts,
                    'items' => $items,
                ];
            }),
        ]);
    }

    /**
     * @return JsonResponse
     */
    public function doneMerchant(): JsonResponse
    {
        $data = $this->validate(request(), [
            'date_from' => 'nullable|integer',
            'date_to' => 'nullable|integer',
        ]);

        $builderDone = Shipment::query()->where('status', ShipmentStatus::DONE);
        if (isset($data['date_from'])) {
            $builderDone->where('status_at', '>=', Carbon::createFromTimestamp($data['date_from']));
        }
        if (isset($data['date_to'])) {
            $builderDone->where('status_at', '<', Carbon::createFromTimestamp($data['date_to']));
        }
        $doneShipments = $builderDone->get();

        $builderCancel = Shipment::query()->where('is_canceled', 1);
        if (isset($data['date_from'])) {
            $builderCancel->where('is_canceled_at', '>=', Carbon::createFromTimestamp($data['date_from']));
        }
        if (isset($data['date_to'])) {
            $builderCancel->where('is_canceled_at', '<', Carbon::createFromTimestamp($data['date_to']));
        }
        $cancelShipments = $builderCancel->get();

        $shipments = (new Collection())
            ->merge($doneShipments)
            ->merge($cancelShipments);

        $shipments->load(['basketItems', 'delivery.order.discounts']);

        return response()->json([
            'items' => $shipments->map(function (Shipment $shipment) {
                $items = [];
                foreach ($shipment->basketItems as $item) {
                    $price = $item->cost;
                    foreach ($shipment->delivery->order->discounts as $discount) {
                        if (!$discount->merchant_id || $discount->merchant_id != $shipment->merchant_id) {
                            continue;
                        }

                        if (!$discount->items) {
                            continue;
                        }

                        foreach ($discount->items as $discountItem) {
                            if ($discountItem['offer_id'] == $item->offer_id) {
                                $price -= $discountItem['change'];
                            }
                        }
                    }
                    $items[] = [
                        'offer_id' => $item->offer_id,
                        'name' => $item->name,
                        'qty' => $item->qty,
                        'price' => $price,
                    ];
                }

                return [
                    'created_at' => $shipment->delivery->order->created_at->format('Y-m-d H:i:s'),
                    'items' => $items,
                    'order_id' => $shipment->delivery->order->id,
                    'shipment_id' => $shipment->id,
                    'merchant_id' => $shipment->merchant_id,
                    'status' => $shipment->status,
                    'is_canceled' => $shipment->is_canceled,
                    'is_canceled_at' => $shipment->is_canceled_at,
                    'status_at' => $shipment->status_at,
                ];
            }),
        ]);
    }
}

