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
        $orderId = $checkoutOrder->save();
        
        return response()->json([
            'orderId' => $orderId,
        ]);
    }
}
