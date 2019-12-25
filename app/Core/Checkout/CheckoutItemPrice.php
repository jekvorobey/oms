<?php

namespace App\Core\Checkout;

class CheckoutItemPrice
{
    public $offerId;
    public $cost;
    public $price;
    
    public static function fromArray(array $data): self
    {
        $itemPrice = new self();
        @([
            'offerId' => $itemPrice->offerId,
            'cost' => $itemPrice->cost,
            'price' => $itemPrice->price,
        ] = $data);
        
        return $itemPrice;
    }
}
