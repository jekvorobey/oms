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
use App\Services\DocumentService;
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
     * Получить детальную информацию о заказе в зависимости от его типа
     * @param  int  $id
     * @param  Request  $request
     * @param  OrderService  $orderService
     * @return JsonResponse
     * @throws \Pim\Core\PimException
     */
    public function readOne(int $id, Request $request, OrderService $orderService): JsonResponse
    {
        $order = Order::find($id);
        if (!$order) {
            throw new \Exception("Order by id={$id} not found");
        }

        if ($order->isProductOrder()) {
            $reader = new OrderReader();
            $item = $reader->list((new RestQuery($request))->setFilter('id', $id))->first();
        } else {
            $item = $orderService->getPublicEventsOrderInfo($order, true);
        }

        return response()->json([
            'item' => $item->toArray(),
        ]);
    }

    /**
     * @param  int  $id
     * @param  Request  $request
     * @param  DocumentService  $documentService
     * @return JsonResponse
     * @throws \Throwable
     */
    public function tickets(int $id, Request $request, DocumentService $documentService): JsonResponse
    {
        $data = $request->validate([
            'basket_item_id' => 'sometimes|integer',
        ]);

        /** @var Order $order */
        $order = Order::query()->where('id', $id)->with('basket.items')->first();
        if (!$order) {
            throw new \Exception("Order by id={$id} not found");
        }

        $documentDto = $documentService->getOrderPdfTickets($order, $data['basket_item_id'] ?? null);
        if (!$documentDto->success) {
            throw new \Exception("Tickets not formed");
        }

        return response()->json([
            'file_id' => $documentDto->file_id
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
            ->join('delivery', 'orders.id', '=', 'delivery.order_id')
            ->whereIn('delivery.id', $deliveryIds)
            ->select('orders.*', 'delivery.delivery_at', 'delivery.number as delivery_number', 'delivery.receiver_name as receiver_name', 'delivery.delivery_address as delivery_address', 'delivery.status_xml_id as status_xml_id')
            ->offset($offset)
            ->limit($perPage);

        return response()->json($orders->get(), 200);
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

            'status' => ['nullable', Rule::in(OrderStatus::validValues($order->type))],
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

        $dateFrom = isset($data['date_from']) ? $data['date_from'] : Carbon::now()->firstOfMonth()->format('Y-m-d');
        $dateTo = isset($data['date_from']) ? $data['date_from'] : Carbon::now()->format('Y-m-d');

        $orders = Order::query()
            ->whereIn('payment_status', [PaymentStatus::HOLD, PaymentStatus::PAID])
            ->where('payment_status_at', '>=', $dateFrom)
            ->where('payment_status_at', '<', $dateTo)
            ->get();

        $orders->load(['basket.items', 'discounts']);

        $items = [];
        foreach ($orders as $order) {
            foreach ($order->basket->items as $item) {
                $merchantId = isset($item->product['merchant_id']) ? $item->product['merchant_id'] : 'n/a';
                $price = $item->price;
                $cost = $item->cost;
                $bonusSpent = $item->bonus_spent;
                $bonusDiscount = $item->bonus_discount;
                $discounts = [];
                foreach ($order->discounts as $orderDiscount) {
                    if (!$orderDiscount->items) { continue; }
                    //спонсор скидки, null = маркетплэйс
                    $discount['sponsor'] = $orderDiscount->merchant_id ;
                    $discount['type'] = $orderDiscount->type;
                    $discount['discount_id'] = $orderDiscount->discount_id;
                    $discount['order_change'] = $orderDiscount->change;

                    foreach ($orderDiscount->items as $discountItem) {
                        $discount['change'] = 0;
                        if ($discountItem['offer_id'] == $item->offer_id) {
                            $discount['change'] += $orderDiscount->change;
                        }
                    }

                    if ($orderDiscount->merchant_id) {
                        $discounts['merchant']['discounts'][] = $discount;
                        $discounts['merchant']['sum'] = array_sum(array_column($discounts['marketplace']['discounts'], 'change'));
                    } else {
                        $discounts['marketplace']['discounts'][] = $discount;
                        $discounts['marketplace']['sum'] = array_sum(array_column($discounts['marketplace']['discounts'], 'change'));
                    }
                }

                $items[] = [
                    'order_id' => $order->id,
                    'offer_id' => $item->offer_id,
                    'name' => $item->name,
                    'qty' => $item->qty,
                    'cost' => $cost,
                    'price' => $price,
                    'discounts' => $discounts,
                    'bonus_spent' => $bonusSpent,
                    'bonus_discount' => $bonusDiscount,
                    'created_at' => $order->created_at->format('Y-m-d H:i:s'),
                    'shipment_id' => $order->id,
                    'merchant_id' => $merchantId,
                    'status' => $order->status,
                    'is_canceled' => $order->is_canceled,
                    'is_canceled_at' => $order->is_canceled_at,
                    'status_at' => $order->payment_status_at,
                ];
            }
        }

        return response()->json(['items' => $items]);

    }
}

