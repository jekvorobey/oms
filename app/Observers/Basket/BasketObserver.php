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
     * Handle the order "deleting" event.
     * @param  Basket $basket
     * @throws \Exception
     */
    public function deleting(Basket $basket)
    {
        foreach ($basket->items as $item) {
            $item->delete();
        }
    }
}
