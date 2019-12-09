<?php

namespace App\Models\Checkout;

class UserAddress implements \JsonSerializable
{
    public $cityId;
    public $description;
    public $id;
    
    public function __construct(int $id, string $description, string $cityId)
    {
        $this->id = $id;
        $this->description = $description;
        $this->cityId = $cityId;
    }
    
    public function jsonSerialize()
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'cityId' => $this->cityId,
        ];
    }
}
