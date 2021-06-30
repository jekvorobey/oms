<?php

namespace App\Core\Checkout;

class CheckoutShipment
{
    /** @var int */
    public $merchantId;
    /** @var int */
    public $storeId;
    /** @var float */
    public $cost;
    /** @var string */
    public $date;
    /** @var string */
    public $psd;
    /** @var int[] */
    public $items;

    public static function fromArray(array $data): self
    {
        $shipment = new self();
        @([
            'merchantId' => $shipment->merchantId,
            'storeId' => $shipment->storeId,
            'cost' => $shipment->cost,
            'date' => $shipment->date,
            'psd' => $shipment->psd,
            'items' => $shipment->items,
        ] = $data);

        return $shipment;
    }
}
