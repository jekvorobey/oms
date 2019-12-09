<?php

namespace App\Models\Checkout;

use App\Models\Catalog\Product;

class DeliveryShipment implements \JsonSerializable
{
    /** @var int */
    public $id;
    /** @var string */
    public $selectedDate;
    /** @var array */
    public $availableDates;
    /** @var Product[] */
    public $items;
    
    /**
     * DeliveryShipment constructor.
     * @param int $id
     * @param string $selectedDate
     * @param array $availableDates
     */
    public function __construct(int $id, string $selectedDate, array $availableDates)
    {
        $this->id = $id;
        $this->selectedDate = $selectedDate;
        $this->availableDates = $availableDates;
    }
    
    public static function fromRequest($shipmentData)
    {
        $shipment = new self($shipmentData['id'], $shipmentData['selectedDate'], $shipmentData['availableDates']);
        foreach ($shipmentData['items'] as $productData) {
            $product = new Product($productData['id']);
            $shipment->addItem($product);
        }
        
        return $shipment;
    }
    
    public function addItem(Product $product)
    {
        $this->items[] = $product;
    }
    
    public function jsonSerialize()
    {
        return [
            'id' => $this->id,
            'selectedDate' => $this->selectedDate,
            'availableDates' => $this->availableDates,
            'items' => $this->items,
        ];
    }
}
