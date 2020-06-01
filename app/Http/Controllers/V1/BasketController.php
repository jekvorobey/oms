<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Basket\Basket;
use App\Services\BasketService;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class BasketController
 * @package App\Http\Controllers\V1
 */
class BasketController extends Controller
{
    /**
     * @param int $customerId
     * @param Request $request
     * @param BasketService $basketService
     * @return JsonResponse
     */
    public function getCurrentBasket(int $customerId, Request $request, BasketService $basketService): JsonResponse
    {
        $data = $this->validate($request, [
            'type' => 'required|integer'
        ]);

        $basket = $basketService->findFreeUserBasket($data['type'], $customerId);
        $response = [
            'id' => $basket->id,
        ];
        if ($request->get('items')) {
            $response['items'] = $this->getItems($basket);
        }

        return response()->json($response);
    }

    /**
     * @param int $basketId
     * @param int $offerId
     * @param Request $request
     * @param BasketService $basketService
     * @return JsonResponse
     * @throws \Exception
     */
    public function setItemByBasket(int $basketId, int $offerId, Request $request, BasketService $basketService): JsonResponse
    {
        $basket = $basketService->getBasket($basketId);
        if (!$basket) {
            throw new NotFoundHttpException('basket not found');
        }

        return $this->setItem($basketId, $offerId, $request);
    }

    /**
     * @param int $orderId
     * @param int $offerId
     * @param Request $request
     * @param OrderService $orderService
     * @return JsonResponse
     * @throws \Exception
     */
    public function setItemByOrder(int $orderId, int $offerId, Request $request, OrderService $orderService): JsonResponse
    {
        $order = $orderService->getOrder($orderId);
        if (!$order) {
            throw new NotFoundHttpException('order not found');
        }

        return $this->setItem($order->basket_id, $offerId, $request);
    }

    /**
     * @param int $basketId
     * @param int $offerId
     * @param Request $request
     *
     * @return JsonResponse
     * @throws \Exception
     */
    protected function setItem(int $basketId, int $offerId, Request $request): JsonResponse
    {
        /** @var BasketService $basketService */
        $basketService = resolve(BasketService::class);

        $data = $request->all();
        $validator = Validator::make($data, [
            'referrer_id' => 'nullable|integer',
            'qty' => 'integer',
            'product' => 'array',
            'product.store_id' => 'nullable|integer',
            'product.bundle_id' => 'nullable|integer',
        ]);
        if ($validator->fails()) {
            throw new BadRequestHttpException($validator->errors()->first());
        }
        $ok = $basketService->setItem($basketId, $offerId, $data);
        if (!$ok) {
            throw new HttpException(500, 'unable to save basket item');
        }
        $response = [];
        if ($request->get('items')) {
            $response['items'] = $this->getItems($basketService->getBasket($basketId));
        }

        return response()->json($response);
    }

    /**
     * @param int $basketId
     * @param Request $request
     * @param BasketService $basketService
     * @return JsonResponse
     */
    public function getBasket(int $basketId, Request $request, BasketService $basketService): JsonResponse
    {
        $basket = $basketService->getBasket($basketId);
        $response = [
            'id' => $basket->id,
        ];
        if ($request->get('items')) {
            $response['items'] = $this->getItems($basket);
        }

        return response()->json($response);
    }

    /**
     * @param int $basketId
     * @param BasketService $basketService
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response
     * @throws \Exception
     */
    public function dropBasket(int $basketId, BasketService $basketService): Response
    {
        if (!$basketService->deleteBasket($basketId)) {
            throw new HttpException(500, 'unable to delete basket');
        }

        return response('', 204);
    }

    /**
     * @param  int  $basketId
     * @param  Request  $request
     * @param BasketService $basketService
     * @return \Illuminate\Contracts\Routing\ResponseFactory|Response
     */
    public function commitItemsPrice(int $basketId, Request $request, BasketService $basketService): Response
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

    /**
     * @param Basket $basket
     * @return array
     */
    protected function getItems(Basket $basket): array
    {
        return $basket->items->toArray();
    }
}

