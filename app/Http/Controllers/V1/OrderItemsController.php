<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class OrderItemsController extends Controller
{
    public function add(int $id, Request $request)
    {
        /** @var Order $order */
        $order = Order::find($id);
        if (!$order) {
            throw new NotFoundHttpException('order not found');
        }
        
        $data = $request->all();
        $validator = Validator::make($data, [
            'items' => 'required|array',
            'items.*.offer_id' => 'required|integer',
            'items.*.name' => 'required|string',
            'items.*.qty' => 'required|integer'
        ]);
        if ($validator->fails()) {
            throw new BadRequestHttpException($validator->errors()->first());
        }
        
        $basket = $order->basket;
        if (!$basket) {
            $basket = $order->createBasket();
            if (!$basket) {
                throw new HttpException(500, 'unable to save basket for order');
            }
        }
        foreach ($data['items'] as ['offer_id' => $offerId, 'name' => $name, 'qty' => $qty]) {
            $item = $basket->addItem($offerId, $name, $qty);
            if (!$item) {
                throw new HttpException(500, 'unable to save basket item');
            }
        }
        
        return response('', 204);
    }
}
