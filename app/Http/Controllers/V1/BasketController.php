<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Basket\Basket;
use App\Models\Order\Order;
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
     * @OA\Get(
     *     path="/api/v1/baskets",
     *     tags={"basket"},
     *     summary="Получить текущую корзину пользователя",
     *     operationId="getCurrentBasket",
     *     @OA\Response(
     *         response=200,
     *         description="OK",
     *         @OA\JsonContent(
     *             @OA\Property(property="id",type="integer"),
     *             @OA\Property(property="items",type="array", @OA\Items(
     *                  ref="#/components/schemas/BasketItem"
     *             )),
     *         )
     *     ),
     * )
     * @param int $userId
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCurrentBasket(int $userId, Request $request): JsonResponse
    {
        $types = (array)$request->get('type', [
            Basket::TYPE_PRODUCT,
            Basket::TYPE_MASTER
        ]);
        $result = [];
        foreach ($types as $type) {
            $basket = Basket::findFreeUserBasket($type, $userId);
            $item = [
                'id' => $basket->id,
            ];
            if ($request->get('items')) {
                $item['items'] = $this->getItems($basket);
            }
            
            $result[] = $item;
        }
        
        
        return response()->json($result);
    }
    
    /**
     * @param int $basketId
     * @param int $offerId
     * @param Request $request
     * @return JsonResponse
     * @throws \Exception
     */
    public function setItemByBasket(int $basketId, int $offerId, Request $request): JsonResponse
    {
        /** @var Basket $basket */
        $basket = Basket::find($basketId);
        if (!$basket) {
            throw new NotFoundHttpException('basket not found');
        }
        
        return $this->setItem($basket, $offerId, $request);
    }
    
    /**
     * @param int $id
     * @param int $offerId
     * @param Request $request
     * @return JsonResponse
     * @throws \Exception
     */
    public function setItemByOrder(int $id, int $offerId, Request $request): JsonResponse
    {
        /** @var Order $order */
        $order = Order::find($id);
        if (!$order) {
            throw new NotFoundHttpException('order not found');
        }
        $basket = $order->getOrCreateBasket();
        
        return $this->setItem($basket, $offerId, $request);
    }
    
    /**
     * @param Basket $basket
     * @param int $offerId
     * @param Request $request
     * @return JsonResponse
     * @throws \Exception
     */
    protected function setItem(Basket $basket, int $offerId, Request $request): JsonResponse
    {
        $data = $request->all();
        $validator = Validator::make($data, [
            'qty' => 'integer',
        ]);
        if ($validator->fails()) {
            throw new BadRequestHttpException($validator->errors()->first());
        }
        $ok = $basket->setItem($offerId, $data);
        if (!$ok) {
            throw new HttpException(500, 'unable to save basket item');
        }
        $response = [];
        if ($request->get('items')) {
            $response['items'] = $this->getItems($basket);
        }
        
        return response()->json($response);
    }
    
    /**
     * @param int $basketId
     * @param Request $request
     * @return JsonResponse
     */
    public function getBasket(int $basketId, Request $request): JsonResponse
    {
        /** @var Basket $basket */
        $basket = Basket::find($basketId);
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
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response
     * @throws \Exception
     */
    public function dropBasket(int $basketId): Response
    {
        /** @var Basket $basket */
        $basket = Basket::find($basketId);
        $ok = $basket->delete();
        if (!$ok) {
            throw new HttpException(500, 'unable to delete basket');
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

