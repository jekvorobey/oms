<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Basket\Basket;
use App\Models\Order\Order;
use Illuminate\Http\Request;
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
    public function getCurrentBasket(int $userId, Request $request)
    {
        $basket = Basket::findFreeUserBasket($userId);
        $response = [
            'id' => $basket->id
        ];
        if ($request->get('items')) {
            $response['items'] = $this->getItems($basket);
        }
        return response()->json($response);
    }
    
    public function setItemByBasket(int $basketId, int $offerId, Request $request)
    {
        $basket = Basket::find($basketId);
        if (!$basket) {
            throw new NotFoundHttpException('basket not found');
        }
        return $this->setItem($basket, $offerId, $request);
    }
    
    public function setItemByOrder(int $id, int $offerId, Request $request)
    {
        /** @var Order $order */
        $order = Order::find($id);
        if (!$order) {
            throw new NotFoundHttpException('order not found');
        }
        $basket = $order->getOrCreateBasket();
        return $this->setItem($basket, $offerId, $request);
    }
    
    protected function setItem(Basket $basket, int $offerId, Request $request)
    {
        $data = $request->all();
        $validator = Validator::make($data, [
            'name' => 'nullable|string',
            'qty' => 'nullable|integer'
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
    
    public function getBasket(int $basketId, Request $request)
    {
        $basket = Basket::find($basketId);
        $response = [
            'id' => $basket->id
        ];
        if ($request->get('items')) {
            $response['items'] = $this->getItems($basket);
        }
        return response()->json($response);
    }
    
    public function dropBasket(int $basketId)
    {
        $basket = Basket::find($basketId);
        $ok = $basket->delete();
        if (!$ok) {
            throw new HttpException(500, 'unable to delete basket');
        }
        return response('', 204);
    }
    
    protected function getItems(Basket $basket)
    {
        return $basket->items->toArray();
    }
}
