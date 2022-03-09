<?php

namespace App\Http\Controllers\V1\Basket;

use App\Services\BasketService\GuestBasketService;

class GuestBasketController extends BasketController
{
    public function __construct()
    {
        $this->basketService = resolve(GuestBasketService::class);
    }
}
