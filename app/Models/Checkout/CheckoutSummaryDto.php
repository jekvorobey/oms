<?php

namespace App\Models\Checkout;

class CheckoutSummaryDto implements \JsonSerializable
{
    public $cartCost = 0;
    public $deliveryCost = 0;
    
    public $cartDiscount = 0;
    public $promoDiscount = 0;
    public $bonusDiscount = 0;
    public $certDiscount = 0;
    
    public $newBonus = 0;
    public $spentBonus = 0;
    
    public function price(): float
    {
        return $this->cartCost + $this->deliveryCost - $this->discount();
    }
    
    public function discount()
    {
        return $this->promoDiscount + $this->bonusDiscount + $this->certDiscount + $this->cartDiscount;
    }
    
    public function jsonSerialize()
    {
        return [
            'sum' => priceFormat($this->cartCost),
            
            'promoDiscount' => priceFormat($this->promoDiscount, true),
            'certDiscount' => priceFormat($this->certDiscount, true),
            'bonusDiscount' => priceFormat($this->bonusDiscount, true),
            
            'delivery' => $this->deliveryCost ? priceFormat($this->deliveryCost) : 'Бесплатно',
            'total' => priceFormat($this->price()),
            
            'bonusGet' => $this->newBonus ?? 0,
            'bonusSpent' => $this->spentBonus ?? 0,
        ];
    }
}
