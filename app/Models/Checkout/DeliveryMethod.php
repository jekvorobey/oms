<?php

namespace App\Models\Checkout;

class DeliveryMethod implements \JsonSerializable
{
    public $id;
    public $title;
    public $price;
    public $description;
    public $methods;
    
    public function __construct(int $id, string $title)
    {
        $this->id = $id;
        $this->title = $title;
    }
    
    public function addOption(int $id, string $name)
    {
        $this->methods[] = [
            'id' => $id,
            'title' => $name
        ];
    }
    
    public function setInfo(float $price, string $description)
    {
        $this->price = $price;
        $this->description = $description;
    }
    
    public function jsonSerialize()
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'price' => priceFormat($this->price),
            'description' => $this->description,
            'methods' => $this->methods
        ];
    }
}
