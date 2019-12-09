<?php

namespace App\Models\Checkout;

class Recipient implements \JsonSerializable
{
    public $phone;
    public $email;
    public $id;
    public $name;
    
    public function __construct(int $id, string $name, string $phone, string $email)
    {
        $this->id = $id;
        $this->name = $name;
        $this->phone = $phone;
        $this->email = $email;
    }
    
    public function jsonSerialize()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone
        ];
    }
}
