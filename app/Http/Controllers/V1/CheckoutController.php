<?php

namespace App\Http\Controllers\V1;

use App\Core\Checkout\CheckoutOrder;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Class CheckoutController
 * @package App\Http\Controllers\V1
 */
class CheckoutController extends Controller
{
    /**
     * @param  Request  $request
     * @return JsonResponse
     */
    public function commit(Request $request): JsonResponse
    {
        $checkoutOrder = CheckoutOrder::fromArray($request->all());
        try {
            [$orderId, $orderNumber] = $checkoutOrder->save();
        } catch (\Exception $e) {
            throw new HttpException($e->getCode() ? : 500, $e->getMessage());
        }

        return response()->json([
            'item' => [
                'order_id' => $orderId,
                'order_number' => $orderNumber,
            ],
        ]);
    }
}
