<?php

namespace App\Http\Controllers\V1\Basket;

use App\Core\Basket\BasketReader;
use App\Core\Order\OrderReader;
use App\Http\Requests\ReplaceBasketRequest;
use App\Services\BasketService\GuestBasketService;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Greensight\CommonMsa\Rest\RestQuery;

class ListBasketController extends BasketController
{
    public function list(Request $request): JsonResponse
    {
        $reader = new BasketReader();

        return response()->json([
            'items' => $reader->list(new RestQuery($request)),
        ]);
    }

    public function count(Request $request): JsonResponse
    {
        $reader = new BasketReader();

        return response()->json($reader->count(new RestQuery($request)));
    }
}
