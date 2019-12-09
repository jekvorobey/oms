<?php

namespace App\Models\Checkout;

class DeliveryType implements \JsonSerializable
{
    /** @var int */
    public $id;
    /** @var int */
    public $methodId;
    /** @var string */
    public $title;
    /** @var string */
    public $description;
    /** @var DeliveryShipment[] */
    public $items;
    
    public function __construct(int $id, int $methodId, string $title, string $description)
    {
        $this->id = $id;
        $this->methodId = $methodId;
        $this->title = $title;
        $this->description = $description;
    }
    
    public static function fromRequest(array $data)
    {
        $type = new self($data['id'], $data['methodId'], $data['title'], $data['description']);
        foreach ($data['items'] as $shipmentData) {
            $shipment = DeliveryShipment::fromRequest($shipmentData);
            $type->addShipment($shipment);
        }
        return $type;
    }
    
    public function addShipment(DeliveryShipment $shipment)
    {
        $this->items[] = $shipment;
    }
    
    public function shipmentById(int $id): ?DeliveryShipment
    {
        $shipments = array_filter($this->items, function (DeliveryShipment $shipment) use ($id) {
            return $shipment->id == $id;
        });
        
        return $shipments ? current($shipments) : null;
    }
    
    public function jsonSerialize()
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'methodId' => $this->methodId,
            'description' => $this->description,
            'items' => $this->items,
        ];
    }
}
