<?php

namespace App\Models\Cart;

class CartCheckout implements \JsonSerializable
{
    public $cost;
    public $cartDiscount = 0;
    public $promoDiscount = 0;
    public $bonusGet;
    
    /**
     * CartCheckout constructor.
     * @param float $price
     * @param $cartDiscount
     * @param $promoDiscount
     * @param $bonusGet
     */
    public function __construct($price, $cartDiscount, $promoDiscount, $bonusGet)
    {
        $this->cost = $price;
        $this->cartDiscount = $cartDiscount;
        $this->bonusGet = $bonusGet;
        $this->promoDiscount = $promoDiscount;
    }
    
    public function discount()
    {
        return $this->cartDiscount + $this->promoDiscount;
    }
    
    public function price()
    {
        return $this->cost - $this->discount();
    }
    
    public function jsonSerialize()
    {
        return [
            'sum' => priceFormat($this->cost),
            'promoDiscount' => priceFormat($this->promoDiscount),
            'bonusGet' => $this->bonusGet ?? 0,
            'total' => priceFormat($this->price()),
        ];
    }
}
