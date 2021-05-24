<?php

namespace App\Observers\Basket;

use App\Models\Basket\Basket;

/**
 * Class BasketObserver
 * @package App\Observers\Basket
 */
class BasketObserver
{
    /**
     * Handle the basket "deleting" event.
     * @throws \Exception
     */
    public function deleting(Basket $basket)
    {
        foreach ($basket->items as $item) {
            $item->delete();
        }
    }
}
