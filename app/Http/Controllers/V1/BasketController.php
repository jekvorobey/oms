<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\GetCurrentBasketRequest;
use App\Http\Requests\SetItemToBasketRequest;
use App\Models\Basket\Basket;
use App\Models\Basket\BasketItem;
use App\Models\Order\Order;
use App\Models\Order\OrderStatus;
use App\Services\BasketService\CustomerBasketService;
use App\Services\BasketService\GuestBasketService;
use App\Services\OrderService;
use Greensight\Customer\Services\CustomerService\CustomerService;
use Greensight\Message\Services\ServiceNotificationService\ServiceNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Class BasketController
 * @package App\Http\Controllers\V1
 */
class BasketController extends Controller
{
    /**
     * @OA\Get(
     *     path="api/v1/baskets/by-customer/{customerId}",
     *     tags={"Корзина"},
     *     description="Получить текущую корзину",
     *     @OA\Parameter(name="customerId", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *      required=true,
     *      description="",
     *      @OA\JsonContent(
     *          required={"type"},
     *          @OA\Property(property="type", type="integer", format="text", example="0"),
     *      ),
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="",
     *         @OA\JsonContent(ref="#/components/schemas/Basket")
     *     )
     * )
     */
    public function getCurrentBasket(
        int $customerId,
        GetCurrentBasketRequest $request,
        CustomerBasketService $basketService
    ): JsonResponse {
        $data = $request->all();

        $basket = $basketService->findFreeUserBasket($data['type'], $customerId);

        return $this->getCurrentBasketResponse($basket, $request);
    }

    /**
     * @OA\Put(
     *     path="api/v1/baskets/{basketId}/items/{offerId}",
     *     tags={"Корзина"},
     *     description="Добавить товар в корзину",
     *     @OA\Parameter(name="basketId", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="offerId", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *      required=true,
     *      description="",
     *      @OA\JsonContent(
     *          required={"type"},
     *          @OA\Property(property="items[0].offerId", type="integer", format="text", example="0"),
     *          @OA\Property(property="items[0].cost", type="numeric", format="text", example="0"),
     *          @OA\Property(property="items[0].price", type="numeric", format="text", example="0"),
     *          @OA\Property(property="referrer_id", type="integer", format="text", example="0"),
     *          @OA\Property(property="qty", type="numeric", format="text", example="0"),
     *          @OA\Property(property="product['store_id']", type="integer", example="0"),
     *          @OA\Property(property="product['bundle_id']", type="integer", example="0"),
     *      ),
     *     ),
     *     @OA\Response(response="200", description="", @OA\JsonContent(ref="#/components/schemas/Basket")),
     *     @OA\Response(response="404", description="basket not found"),
     * )
     * @throws \Exception
     */
    public function setItemByBasket(int $basketId, int $offerId, SetItemToBasketRequest $request): JsonResponse
    {
        return $this->setItem($basketId, $offerId, $request);
    }

    /**
     * @OA\Put(
     *     path="api/v1/orders/{id}/items/{offerId}",
     *     tags={"Корзина"},
     *     description="Добавить заказ в корзину",
     *     @OA\Parameter(name="id", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="offerId", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *      required=true,
     *      description="",
     *      @OA\JsonContent(
     *          required={"type"},
     *          @OA\Property(property="referrer_id", type="integer", format="text", example="0"),
     *          @OA\Property(property="qty", type="numeric", format="text", example="0"),
     *          @OA\Property(property="product['store_id']", type="integer", example="0"),
     *          @OA\Property(property="product['bundle_id']", type="integer", example="0"),
     *      ),
     *     ),
     *     @OA\Response(response="200", description="", @OA\JsonContent(ref="#/components/schemas/Basket")),
     *     @OA\Response(response="404", description="order not found"),
     * )
     *
     * @throws \Exception
     */
    public function setItemByOrder(
        int $orderId,
        int $offerId,
        SetItemToBasketRequest $request,
        OrderService $orderService
    ): JsonResponse {
        $order = $orderService->getOrder($orderId);

        return $this->setItem($order->basket_id, $offerId, $request);
    }

    /**
     * @throws \Exception
     */
    protected function setItem(int $basketId, int $offerId, SetItemToBasketRequest $request): JsonResponse
    {
        /** @var CustomerBasketService $basketService */
        $basketService = resolve(CustomerBasketService::class);
        $basket = $basketService->getBasket($basketId);

        $data = $request->all();

        $respondWithItems = (bool) ($data['items'] ?? false);
        unset($data['items']);

        $ok = $basketService->setItem($basket, $offerId, $data);
        if (!$ok) {
            throw new HttpException(500, 'unable to save basket item');
        }

        $response = [];
        if ($respondWithItems) {
            $response['items'] = $this->getItems($basket);
        }

        return response()->json($response);
    }

    /**
     * @OA\Get(
     *     path="api/v1/baskets/{basketId}",
     *     tags={"Корзина"},
     *     description="Получить корзину с id",
     *     @OA\Parameter(name="id", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *      required=true,
     *      description="",
     *      @OA\JsonContent(
     *          required={"type"},
     *          @OA\Property(property="items", type="json", example="{}"),
     *      ),
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="",
     *         @OA\JsonContent(
     *             @OA\Property(property="items", type="array", @OA\Items(ref="#/components/schemas/Basket"))
     *         )
     *     )
     * )
     */
    public function getBasket(int $basketId, Request $request, CustomerBasketService $basketService): JsonResponse
    {
        $basket = $basketService->getBasket($basketId);

        return $this->getBasketResponse($basket, $request);
    }

    /**
     * @OA\Delete(
     *     path="api/v1/baskets/{basketId}",
     *     tags={"Корзина"},
     *     description="Удалить корзину",
     *     @OA\Parameter(name="basketId", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\Response(response="204", description=""),
     *     @OA\Response(response="500", description="unable to delete basket"),
     * )
     * @throws \Exception
     */
    public function dropBasket(int $basketId, CustomerBasketService $basketService): Response
    {
        $basket = $basketService->getBasket($basketId);

        if (!$basketService->deleteBasket($basket)) {
            throw new HttpException(500, 'unable to delete basket');
        }

        return response('', 204);
    }

    /**
     * @OA\Put(
     *     path="api/v1/baskets/{basketId}/commit",
     *     tags={"Корзина"},
     *     description="Редактирование стоимости, цены, заказа в корзине",
     *     @OA\Parameter(name="basketId", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *      required=true,
     *      description="",
     *      @OA\JsonContent(
     *          required={"type"},
     *          @OA\Property(property="items[0].offerId", type="integer", format="text", example="0"),
     *          @OA\Property(property="items[0].cost", type="numeric", format="text", example="0"),
     *          @OA\Property(property="items[0].price", type="numeric", format="text", example="0"),
     *      ),
     *     ),
     *     @OA\Response(response="204", description="Данные сохранены"),
     *     @OA\Response(response="404", description="product not found"),
     * )
     */
    public function commitItemsPrice(int $basketId, Request $request, CustomerBasketService $basketService): Response
    {
        $data = $this->validate($request, [
            'items' => 'required|array',
            'items.*' => 'array',
            'items.*.offerId' => 'required|integer',
            'items.*.cost' => 'required|numeric',
            'items.*.price' => 'required|numeric',
        ]);
        //todo Скорее всего надо перенести код ниже в BasketService
        $basket = $basketService->getBasket($basketId);
        $priceMap = [];
        foreach ($data['items'] as $dataItem) {
            $priceMap[$dataItem['offerId']] = $dataItem;
        }
        foreach ($basket->items as $item) {
            if (!isset($priceMap[$item->offer_id])) {
                continue;
            }
            [
                'cost' => $cost,
                'price' => $price,
            ] = $priceMap[$item->offer_id];
            $item->cost = $cost;
            $item->price = $price;
            $item->save();
        }

        return response('', 204);
    }

    protected function getItems(Basket $basket): array
    {
        return $basket->items->toArray();
    }

    /**
     * @OA\Get(
     *     path="api/v1/baskets/qty-by-offer-ids",
     *     tags={"Корзина"},
     *     description="Получить количество корзин для идентификаторов офферов",
     *     @OA\RequestBody(
     *      required=true,
     *      description="",
     *      @OA\JsonContent(
     *          required={"offer_ids"},
     *          @OA\Property(property="offer_ids", type="string", example="[1,2,3]"),
     *      ),
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="",
     *          @OA\JsonContent(
     *              @OA\Property(property="baskets_qty", type="integer", example="0"),
     *          )
     *     )
     * )
     */
    protected function qtyByOfferIds(Request $request): JsonResponse
    {
        $data = $this->validate($request, [
            'offer_ids' => 'required|array',
            'offer_ids.*' => 'integer',
        ]);

        $basketsQty = DB::table(with(new Order())->getTable())
            ->leftJoin('basket_items', 'basket_items.basket_id', '=', 'orders.basket_id')
            ->select('basket_items.offer_id', DB::raw('count(*) as total'))
            ->whereIn('basket_items.offer_id', $data['offer_ids'])
            ->where('orders.is_canceled', false)
            ->where('orders.is_problem', false)
            ->whereIn('orders.status', [OrderStatus::IN_PROCESSING, OrderStatus::DELIVERING,
                OrderStatus::READY_FOR_RECIPIENT, OrderStatus::DONE,
            ])
            ->groupBy('offer_id')
            ->pluck('total', 'offer_id')
            ->all();

        return response()->json([
            'baskets_qty' => $basketsQty,
        ]);
    }

    /**
     * @OA\Post(
     *     path="api/v1/baskets/notify-expired/{offer}",
     *     tags={"Корзина"},
     *     description="Уведомления о просроченных предложениях",
     *     @OA\Parameter(name="offer", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\Response(
     *          response="200",
     *          description="",
     *          @OA\JsonContent(
     *              @OA\Property(property="item", type="array", @OA\Items(ref="#/components/schemas/Basket")),
     *              @OA\Property(property="customer", type="json", example="{}"),
     *          )
     *     ),
     *     @OA\Response(response="400", description="Ошибка валидации"),
     *     @OA\Response(response="404", description=""),
     *     @OA\Response(response="500", description="Не удалось сохранить данные"),
     * )
     */
    public function notifyExpiredOffers(int $offer)
    {
        $customerService = app(CustomerService::class);
        $serviceNotificationService = app(ServiceNotificationService::class);

        BasketItem::query()
            ->where('offer_id', $offer)
            ->get()
            ->map(function (BasketItem $basketItem) use ($customerService) {
                return [
                    'item' => $basketItem,
                    'customer' => $customerService->customers(
                        $customerService
                            ->newQuery()
                            ->setFilter('id', $basketItem->basket->customer_id)
                    )->first(),
                ];
            })
            ->each(function ($el) use ($serviceNotificationService) {
                $serviceNotificationService->send(
                    $el['customer']->user_id,
                    'tovardostupnost_kh_tovarov_v_korzine_izmenilas'
                );
            });
    }

    /**
     * @OA\Get(
     *     path="api/v1/baskets/guest/{basketId}",
     *     tags={"Корзина"},
     *     description="Получить корзину с id",
     *     @OA\Parameter(name="basketId", required=true, in="path", @OA\Schema(type="string")),
     *     @OA\RequestBody(
     *      required=true,
     *      description="",
     *      @OA\JsonContent(
     *          required={"type"},
     *          @OA\Property(property="items", type="json", example="{}"),
     *      ),
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="",
     *         @OA\JsonContent(
     *             @OA\Property(property="items", type="array", @OA\Items(ref="#/components/schemas/Basket"))
     *         )
     *     )
     * )
     */
    public function getBasketFromGuest(int $basketId, Request $request, GuestBasketService $basketService): JsonResponse
    {
        $basket = $basketService->getBasket($basketId);

        return $this->getBasketResponse($basket, $request);
    }

    public function getCurrentBasketFromGuest(
        string $customerId,
        GetCurrentBasketRequest $request,
        GuestBasketService $basketService
    ): JsonResponse {
        $data = $request->all();

        $basket = $basketService->findFreeUserBasket($data['type'], $customerId);

        return $this->getCurrentBasketResponse($basket, $request);
    }

    /**
     * @OA\Delete(
     *     path="api/v1/baskets/guest/{basketId}",
     *     tags={"Корзина"},
     *     description="Удалить корзину",
     *     @OA\Parameter(name="basketId", required=true, in="path", @OA\Schema(type="string")),
     *     @OA\Response(response="204", description=""),
     *     @OA\Response(response="500", description="unable to delete basket"),
     * )
     * @throws \Exception
     */
    public function dropBasketFromGuest(int $basketId, GuestBasketService $basketService): Response
    {
        $basket = $basketService->getBasket($basketId);

        if (!$basketService->dropBasket($basket)) {
            throw new HttpException(500, 'unable to delete basket from cache');
        }

        return response('', 204);
    }

    /**
     * @OA\Put(
     *     path="api/v1/baskets/guest/{basketId}/items/{offerId}",
     *     tags={"Корзина"},
     *     description="Добавить товар в корзину",
     *     @OA\Parameter(name="basketId", required=true, in="path", @OA\Schema(type="string")),
     *     @OA\Parameter(name="offerId", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *      required=true,
     *      description="",
     *      @OA\JsonContent(
     *          required={"type"},
     *          @OA\Property(property="items[0].offerId", type="integer", format="text", example="0"),
     *          @OA\Property(property="items[0].cost", type="numeric", format="text", example="0"),
     *          @OA\Property(property="items[0].price", type="numeric", format="text", example="0"),
     *          @OA\Property(property="referrer_id", type="integer", format="text", example="0"),
     *          @OA\Property(property="qty", type="numeric", format="text", example="0"),
     *          @OA\Property(property="product['store_id']", type="integer", example="0"),
     *          @OA\Property(property="product['bundle_id']", type="integer", example="0"),
     *      ),
     *     ),
     *     @OA\Response(response="200", description="", @OA\JsonContent(ref="#/components/schemas/Basket")),
     *     @OA\Response(response="404", description="basket not found"),
     * )
     * @throws \Exception
     */
    public function setItemByGuestBasket(int $basketId, int $offerId, SetItemToBasketRequest $request): JsonResponse
    {
        return $this->setItemToGuest($basketId, $offerId, $request);
    }

    protected function setItemToGuest(int $basketId, int $offerId, SetItemToBasketRequest $request): JsonResponse
    {
        /** @var GuestBasketService $basketService */
        $basketService = resolve(GuestBasketService::class);
        $basket = $basketService->getBasket($basketId);

        $data = $request->all();

        $respondWithItems = (bool) ($data['items'] ?? false);
        unset($data['items']);

        $ok = $basketService->setItem($basket, $offerId, $data);
        if (!$ok) {
            throw new HttpException(500, 'unable to save basket item');
        }

        $response = [];
        if ($respondWithItems) {
            $response['items'] = $this->getItems($basket);
        }

        return response()->json($response);
    }

    private function getBasketResponse(Basket $basket, Request $request): JsonResponse
    {
        $response = [
            'id' => $basket->id,
            'type' => $basket->type,
            'customer_id' => $basket->customer_id,
        ];
        if ($request->get('items')) {
            $response['items'] = $this->getItems($basket);
        }

        return response()->json($response);
    }

    private function getCurrentBasketResponse(Basket $basket, GetCurrentBasketRequest $request): JsonResponse
    {
        $response = [
            'id' => $basket->id,
        ];
        if ($request->get('items')) {
            $response['items'] = $this->getItems($basket);
        }

        return response()->json($response);
    }
}
