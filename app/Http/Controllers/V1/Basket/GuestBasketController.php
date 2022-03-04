<?php

namespace App\Http\Controllers\V1\Basket;

use App\Http\Requests\ReplaceBasketRequest;
use App\Services\BasketService\GuestBasketService;
use Illuminate\Http\Response;

class GuestBasketController extends BasketController
{
    public function __construct()
    {
        $this->basketService = resolve(GuestBasketService::class);
    }

    public function replaceBasket(string $guestId, ReplaceBasketRequest $request): Response
    {
        $customerId = $request->get('customerId');
        $this->basketService->replaceToCustomer($guestId, $customerId);

        return response('', 204);
    }
}
