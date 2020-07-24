<?php

namespace App\Core\Checkout;

class CheckoutItemPrice
{
    public $basketItemId;
    public $offerId;
    public $cost;
    public $price;
    public $bonusSpent;
    public $bonusDiscount;

    public static function fromArray(array $data): self
    {
        $itemPrice = new self();
        @([
            'basketItemId' => $itemPrice->basketItemId,
            'offerId' => $itemPrice->offerId,
            'cost' => $itemPrice->cost,
            'price' => $itemPrice->price,
            'bonusSpent' => $itemPrice->bonusSpent,
            'bonusDiscount' => $itemPrice->bonusDiscount,
        ] = $data);

        return $itemPrice;
    }
}
