<?php

namespace App\Http\Controllers\V1;

use App\Core\Checkout\CheckoutOrder;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function commit(Request $request)
    {
        $checkoutOrder = CheckoutOrder::fromArray($request->all());
        [$orderId, $orderNumber] = $checkoutOrder->save();

        return response()->json([
            'item' => [
                'order_id' => $orderId,
                'order_number' => $orderNumber,
            ],
        ]);
    }
}
