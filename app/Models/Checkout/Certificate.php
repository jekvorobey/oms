<?php

namespace App\Models\Checkout;

class Certificate implements \JsonSerializable
{
    public $id;
    public $code;
    public $amount;
    
    public function __construct(int $id, string $code, float $amount)
    {
        $this->id = $id;
        $this->code = $code;
        $this->amount = $amount;
    }
    
    public function jsonSerialize()
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'amount' => $this->amount
        ];
    }
}
