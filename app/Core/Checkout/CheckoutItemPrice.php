<?php

namespace App\Core\Checkout;

class CheckoutItemPrice
{
    public $offerId;
    public $cost;
    public $price;
    public $bonusSpent;
    public $bonusDiscount;

    public static function fromArray(array $data): self
    {
        $itemPrice = new self();
        @([
            'offerId' => $itemPrice->offerId,
            'cost' => $itemPrice->cost,
            'price' => $itemPrice->price,
            'bonusSpent' => $itemPrice->bonusSpent,
            'bonusDiscount' => $itemPrice->bonusDiscount,
        ] = $data);
        
        return $itemPrice;
    }
}
