<?php

namespace App\Models\Checkout;

class ConfirmationType implements \JsonSerializable
{
    /** @var int */
    public $id;
    /** @var string */
    public $title;
    /** @var string */
    public $type;
    
    public function __construct(int $id, string $title, string $type)
    {
        $this->id = $id;
        $this->title = $title;
        $this->type = $type;
    }
    
    public function jsonSerialize()
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->type
        ];
    }
}
