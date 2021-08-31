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
     * @OA\Get(
     *     path="api/v1/orders",
     *     tags={"Заказы"},
     *     description="Получить список заказов",
     *     @OA\Response(
     *         response="200",
     *         description="",
     *         @OA\JsonContent(
     *             @OA\Property(property="items", type="array", @OA\Items(ref="#/components/schemas/Order"))
     *         )
     *     )
     * )
     * Получить список заказов
     */
    public function read(Request $request): JsonResponse
    {
        $reader = new OrderReader();

        return response()->json([
            'items' => $reader->list(new RestQuery($request)),
        ]);
    }

    /**
     * @OA\Get(
     *     path="api/v1/orders/{id}",
     *     tags={"Заказы"},
     *     description="Получить детальную информацию о заказе в зависимости от его типа",
     *     @OA\Response(
     *         response="200",
     *         description="return Json content",
     *     )
     * )
     *
     * Получить детальную информацию о заказе в зависимости от его типа
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
        } elseif ($order->isCertificateOrder()) {
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
     * @OA\Put(
     *     path="api/v1/orders/{id}/tickets",
     *     tags={"Заказы"},
     *     description="",
     *     @OA\Parameter(name="id", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *      required=true,
     *      description="",
     *      @OA\JsonContent(
     *          required={"name"},
     *          @OA\Property(property="basket_item_id", type="integer"),
     *      ),
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="",
     *         @OA\JsonContent(
     *              @OA\Property(property="basket_item_id", type="integer"),
     *         )
     *     ),
     *     @OA\Response(response="404", description=""),
     * )
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
            throw new \Exception('Tickets not formed');
        }

        return response()->json([
            'file_id' => $documentDto->file_id,
        ]);
    }

    /**
     * @OA\Get(
     *     path=" api/v1/orders/count",
     *     tags={"Заказы"},
     *     description="Получить количество заказов по заданому фильтру",
     *     @OA\Parameter(name="include", required=false, in="query", @OA\Schema(type="array", @OA\Items(type="string")), description="параметр json-api запроса include"),
     *     @OA\Parameter(name="fields", required=false, in="query", @OA\Schema(type="array", @OA\Items(type="string")), description="параметр json-api запроса fields"),
     *     @OA\Parameter(name="filter", required=false, in="query", @OA\Schema(type="array", @OA\Items(type="string")), description="параметр json-api запроса filter"),
     *     @OA\Parameter(name="sort", required=false, in="query", @OA\Schema(type="array", @OA\Items(type="string")), description="параметр json-api запроса sort"),
     *     @OA\Parameter(name="page", required=false, in="query", @OA\Schema(type="array", @OA\Items(type="integer")), description="параметр json-api запроса page"),
     *     @OA\Response(
     *         response="200",
     *         description="",
     *         @OA\JsonContent(
     *             @OA\Property(property="total", type="integer"),
     *             @OA\Property(property="pages", type="integer"),
     *             @OA\Property(property="pageSize", type="integer"),
     *         )
     *     )
     * )
     * Получить количество заказов по заданому фильтру
     */
    public function count(Request $request): JsonResponse
    {
        $reader = new OrderReader();

        return response()->json($reader->count(new RestQuery($request)));
    }

    /**
     * @OA\Post (
     *     path="api/v1/orders/by-offers",
     *     tags={"Заказы"},
     *     description="",
     *     @OA\RequestBody(
     *      required=true,
     *      description="",
     *      @OA\JsonContent(
     *          required={"name"},
     *          @OA\Property(property="offersIds", type="string", example="[1,2,3]"),
     *          @OA\Property(property="perPage", type="integer"),
     *          @OA\Property(property="page", type="integer"),
     *      ),
     *     ),
     *     @OA\Response(
     *         response="201",
     *         description="{}",
     *     ),
     *     @OA\Response(response="400", description="Bad request"),
     *     @OA\Response(response="500", description="unable to save delivery"),
     * )
     */
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
        $offset = ($page - 1) * $perPage;
        $basketIds = BasketItem::whereIn('offer_id', $offersIds)->select('basket_id');
        $basketItemsIds = BasketItem::whereIn('offer_id', $offersIds)->select('id');
        $shipmentIds = ShipmentItem::whereIn('basket_item_id', $basketItemsIds)->select('shipment_id');
        $deliveryIds = Shipment::whereIn('id', $shipmentIds)->select('delivery_id');
        $orders = Order::whereIn('basket_id', $basketIds)
            ->join('delivery', 'orders.id', '=', 'delivery.order_id')
            ->whereIn('delivery.id', $deliveryIds)
            ->select(
                'orders.*',
                'delivery.delivery_at',
                'delivery.number as delivery_number',
                'delivery.receiver_name as receiver_name',
                'delivery.delivery_address as delivery_address',
                'delivery.status_xml_id as status_xml_id'
            )
            ->offset($offset)
            ->limit($perPage);

        return response()->json($orders->get(), 200);
    }

    /**
     * @OA\Put(
     *     path="api/v1/orders/{id}/payments",
     *     tags={"Заказы"},
     *     description="Задать список оплат заказа",
     *     @OA\Parameter(name="id", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *      required=true,
     *      description="",
     *      @OA\JsonContent(
     *          required={"name"},
     *          @OA\Property(property="payments[0].id", type="integer"),
     *          @OA\Property(property="payments[0].sum", type="numeric"),
     *          @OA\Property(property="payments[0].status", type="integer"),
     *          @OA\Property(property="payments[0].type", type="integer"),
     *          @OA\Property(property="payments[0].payment_system", type="integer"),
     *          @OA\Property(property="payments[0].data", type="json"),
     *      ),
     *     ),
     *     @OA\Response(response="204", description="Данные сохранены"),
     *     @OA\Response(response="404", description=""),
     * )
     *
     * Задать список оплат заказа
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

        $data = $request->all();
        /** @var \Illuminate\Validation\Validator $validator */
        $validator = Validator::make($data, [
            'payments' => 'required|array',
            'payments.*.id' => 'nullable|integer',
            'payments.*.sum' => 'nullable|numeric',
            'payments.*.status' => 'nullable|integer',
            'payments.*.type' => 'nullable|integer',
            'payments.*.payment_system' => 'nullable|integer',
            'payments.*.data' => 'nullable|array',
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
     * @OA\Put(
     *     path="api/v1/orders/{id}",
     *     tags={"Заказы"},
     *     description="Обновить заказ.",
     *     @OA\Parameter(name="id", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *      required=true,
     *      description="",
     *      @OA\JsonContent(
     *          required={"name"},
     *          @OA\Property(property="basket_id", type="integer"),
     *          @OA\Property(property="customer_id", type="integer"),
     *          @OA\Property(property="cost", type="number"),
     *          @OA\Property(property="status", type="integer"),
     *          @OA\Property(property="payment_status", type="integer"),
     *          @OA\Property(property="delivery_type", type="integer"),
     *          @OA\Property(property="delivery_address", type="string"),
     *          @OA\Property(property="receiver_name", type="string"),
     *          @OA\Property(property="receiver_phone", type="string"),
     *          @OA\Property(property="receiver_email", type="string"),
     *          @OA\Property(property="manager_comment", type="string"),
     *          @OA\Property(property="confirmation_type", type="integer"),
     *      ),
     *     ),
     *     @OA\Response(response="204", description=""),
     *     @OA\Response(response="404", description="product not found"),
     *     @OA\Response(response="500", description="unable to save order"),
     * )
     * Обновить заказ
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
     * @OA\Delete(
     *     path="api/v1/orders/{id}",
     *     tags={"Заказы"},
     *     description="Удалить заказ",
     *     @OA\Parameter(name="id", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\Response(response="204", description=""),
     *     @OA\Response(response="404", description="order not found"),
     *     @OA\Response(response="500", description="unable to save order"),
     * )
     * Удалить заказ
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
     * @OA\Put(
     *     path="api/v1/orders/{id}/pay",
     *     tags={"Заказы"},
     *     description="Вручную оплатить заказ. Примечание: оплата по заказам автоматически должна поступать от платежной системы!",
     *     @OA\Parameter(name="id", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *      required=true,
     *      description="Изменить значение для public event types.",
     *          @OA\JsonContent(ref="#/components/schemas/Order")
     *     ),
     *     @OA\Response(response="204", description="Данные сохранены"),
     *     @OA\Response(response="404", description="product not found"),
     * )
     *
     * Вручную оплатить заказ
     * Примечание: оплата по заказам автоматически должна поступать от платежной системы!
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
     * @OA\Put(
     *     path="api/v1/orders/{id}/cancel",
     *     tags={"Заказы"},
     *     description="Отменить заказ.",
     *     @OA\Parameter(name="id", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="orderReturnReasonId", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response="204", description=""),
     *     @OA\Response(response="404", description="cargo not found"),
     * )
     *
     * Отменить заказ
     * @throws \Exception
     */
    public function cancel(int $id, Request $request, OrderService $orderService): Response
    {
        $data = $this->validate($request, [
            'orderReturnReason' => 'required|integer|exists:order_return_reasons,id',
        ]);

        $order = $orderService->getOrder($id);
        if (!$order) {
            throw new NotFoundHttpException('order not found');
        }
        if (!$orderService->cancel($order, $data['orderReturnReason'])) {
            throw new HttpException(500);
        }

        return response('', 204);
    }

    /**
     * @OA\Put(
     *     path="api/v1/orders/{id}/comment",
     *     tags={"Заказы"},
     *     description="Добавить комментарий к заказу.",
     *     @OA\RequestBody(
     *      required=true,
     *      description="",
     *      @OA\JsonContent(
     *          @OA\Property(property="text", type="string"),
     *      ),
     *     ),
     *     @OA\Parameter(name="id", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\Response(response="204", description=""),
     *     @OA\Response(response="404", description="cargo not found"),
     * )
     * Добавить комментарий к заказу
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
     * @OA\Get(
     *     path="api/v1/orders/done/referral",
     *     tags={"Заказы"},
     *     description="",
     *     @OA\RequestBody(
     *      required=true,
     *      description="",
     *      @OA\JsonContent(
     *          required={"name"},
     *          @OA\Property(property="date_from", type="string"),
     *          @OA\Property(property="date_to", type="string"),
     *      ),
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="",
     *         @OA\JsonContent(
     *             @OA\Property(property="customer_id", type="integer"),
     *             @OA\Property(property="created_at", type="string"),
     *             @OA\Property(property="number", type="number"),
     *             @OA\Property(property="promo_codes", type="json"),
     *             @OA\Property(property="discounts", type="json"),
     *             @OA\Property(property="items", type="json"),
     *         )
     *     )
     * )
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
                foreach ($order->discounts as $discount) {
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
     * @OA\Get(
     *     path="api/v1/orders/done/merchant",
     *     tags={"Заказы"},
     *     description="Биллинг по отправлениям",
     *     @OA\RequestBody(
     *      required=true,
     *      description="",
     *      @OA\JsonContent(
     *          required={"name"},
     *          @OA\Property(property="date_from", type="string"),
     *          @OA\Property(property="date_to", type="string"),
     *      ),
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="",
     *         @OA\JsonContent(
     *             @OA\Property(property="created_at", type="string"),
     *             @OA\Property(property="items", type="json"),
     *             @OA\Property(property="order_id", type="integer"),
     *             @OA\Property(property="shipment_id", type="integer"),
     *             @OA\Property(property="merchant_id", type="integer"),
     *             @OA\Property(property="status", type="integer"),
     *             @OA\Property(property="is_canceled", type="integer"),
     *             @OA\Property(property="is_canceled_at", type="string"),
     *             @OA\Property(property="status_at", type="string"),
     *         )
     *     )
     * )
     * Биллинг по отправлениям
     */
    public function doneMerchant(): JsonResponse
    {
        $data = $this->validate(request(), [
            'date_from' => 'nullable|integer',
            'date_to' => 'nullable|integer',
        ]);

        $builderDone = Shipment::query()
            ->where('status', ShipmentStatus::DONE)
            ->where('payment_status', 2);
        if (isset($data['date_from'])) {
            $builderDone->where('status_at', '>=', Carbon::createFromTimestamp($data['date_from']));
        }
        if (isset($data['date_to'])) {
            $builderDone->where('status_at', '<', Carbon::createFromTimestamp($data['date_to']));
        }
        $doneShipments = $builderDone->get();

        $builderReturn = Shipment::query()
            ->where('status', ShipmentStatus::RETURNED)
            ->where('payment_status', 2);
        if (isset($data['date_from'])) {
            $builderReturn->where('status_at', '>=', Carbon::createFromTimestamp($data['date_from']));
        }
        if (isset($data['date_to'])) {
            $builderReturn->where('status_at', '<', Carbon::createFromTimestamp($data['date_to']));
        }
        $returnShipments = $builderReturn->get();

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
            ->merge($returnShipments)
            ->merge($cancelShipments);

        $shipments->load(['basketItems', 'delivery.order.discounts']);

        return response()->json([
            'items' => $shipments->map(function (Shipment $shipment) {
                $items = [];
                foreach ($shipment->basketItems as $item) {
                    if (!isset($item->product['merchant_id'])) {
                        continue;
                    }

                    $price = $item->price;
                    $cost = $item->cost;

                    $bonuses['bonus_spent'] = $item->bonus_spent;
                    $bonuses['bonus_discount'] = $item->bonus_discount;

                    $discounts = [];
                    //$price = $item->cost;
                    foreach ($shipment->delivery->order->discounts as $orderDiscount) {
                        if (!$orderDiscount->items) {
                            continue;
                        }
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
                            $discounts['merchant']['sum'] = array_sum(array_column($discounts['merchant']['discounts'], 'change'));
                        } else {
                            $discounts['marketplace']['discounts'][] = $discount;
                            $discounts['marketplace']['sum'] = array_sum(array_column($discounts['marketplace']['discounts'], 'change'));
                        }
                    }
                    $items[] = [
                        'order_id' => $shipment->delivery->order->id,
                        'offer_id' => $item->offer_id,
                        'name' => $item->name,
                        'qty' => $item->qty,
                        'cost' => $cost,
                        'price' => $price,
                        'discounts' => $discounts,
                        'bonuses' => $bonuses,
                        'merchant_id' => $shipment->merchant_id,
                        'is_canceled' => $shipment->is_canceled,
                        'is_canceled_at' => $shipment->is_canceled_at,
                        'status_at' => $shipment->status_at,
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
