<?php

namespace App\Core\Checkout;

class CheckoutDelivery
{
    /** @var int */
    public $deliveryMethod;
    /** @var int */
    public $deliveryService;
    /** @var string */
    public $number;
    /** @var float */
    public $cost;
    /** @var int */
    public $width;
    /** @var int */
    public $height;
    /** @var int */
    public $length;
    /** @var int */
    public $weight;
    /** @var CheckoutShipment[] */
    public $shipments;
    
    public static function fromArray(array $data): self
    {
        $delivery = new self();
        @([
            'deliveryMethod' => $delivery->deliveryMethod,
            'deliveryService' => $delivery->deliveryService,
            'number' => $delivery->number,
            'cost' => $delivery->cost,
            'width' => $delivery->width,
            'height' => $delivery->height,
            'length' => $delivery->length,
            'weight' => $delivery->weight,
            'shipments' => $shipments,
        ] = $data);
        
        foreach ($shipments as $shipmentData) {
            $delivery->shipments[] = CheckoutShipment::fromArray($shipmentData);
        }
        
        return $delivery;
    }
}
