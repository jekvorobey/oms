<?php

namespace App\Core\Checkout;

class CheckoutShipment
{
    /** @var int */
    public $merchantId;
    /** @var int */
    public $storeId;
    /** @var string */
    public $number;
    /** @var float */
    public $cost;
    /** @var string */
    public $date;
    /** @var int[] */
    public $items;
    
    public static function fromArray(array $data): self
    {
        $shipment = new self();
        @([
            'merchantId' => $shipment->merchantId,
            'storeId' => $shipment->storeId,
            'number' => $shipment->number,
            'cost' => $shipment->cost,
            'date' => $shipment->date,
            'items' => $shipment->items
        ] = $data);
        
        return $shipment;
    }
}
