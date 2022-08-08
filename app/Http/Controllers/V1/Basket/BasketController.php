<?php

namespace App\Http\Controllers\V1\Basket;

use App\Http\Controllers\Controller;
use App\Http\Requests\GetCurrentBasketRequest;
use App\Http\Requests\SetItemToBasketRequest;
use App\Models\Basket\Basket;
use App\Services\BasketService\BasketService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

abstract class BasketController extends Controller
{
    protected BasketService $basketService;

    public function getCurrentBasket(int|string $customerId, GetCurrentBasketRequest $request): JsonResponse
    {
        $data = $request->all();

        $basket = $this->basketService->findFreeUserBasket($data['type'], $customerId);

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
     * @throws Exception
     */
    public function setItemByBasket(int $basketId, int $offerId, SetItemToBasketRequest $request): JsonResponse
    {
        return $this->setItem($basketId, $offerId, $request);
    }

    /**
     * @throws Exception
     */
    protected function setItem(int $basketId, int $offerId, SetItemToBasketRequest $request): JsonResponse
    {
        $basket = $this->basketService->getBasket($basketId);

        $data = $request->all();

        $respondWithItems = (bool) ($data['items'] ?? false);
        unset($data['items']);

        $ok = $this->basketService->setItem($basket, $offerId, $data);
        if (!$ok) {
            throw new HttpException(500, 'unable to save basket item');
        }

        $response = [];
        if ($respondWithItems) {
            $response['items'] = $this->getItems($basket);
        }

        return response()->json($response);
    }

    public function getBasket(int $basketId, Request $request): JsonResponse
    {
        $basket = $this->basketService->getBasket($basketId);

        return $this->getBasketResponse($basket, $request);
    }

    public function dropBasket(int $basketId): Response
    {
        $basket = $this->basketService->getBasket($basketId);

        if (!$this->basketService->deleteBasket($basket)) {
            throw new HttpException(500, 'unable to delete basket');
        }

        return response('', 204);
    }

    protected function getBasketResponse(Basket $basket, Request $request): JsonResponse
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

    protected function getCurrentBasketResponse(Basket $basket, GetCurrentBasketRequest $request): JsonResponse
    {
        $response = [
            'id' => $basket->id,
        ];
        if ($request->get('items')) {
            $response['items'] = $this->getItems($basket);
        }

        return response()->json($response);
    }

    protected function getItems(Basket $basket): array
    {
        return $basket->items->toArray();
    }
}
