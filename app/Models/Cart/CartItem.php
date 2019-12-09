<?php

namespace App\Models\Cart;

//use App\Models\Catalog\Product;

class CartItem implements \JsonSerializable
{
    /** @var int */
    private $id;
    /** @var string */
    private $type;
    /** @var int */
    private $count;
//    /** @var Product */
//    public $product;

    public function __construct(int $id, string $type, int $count, $product)
    {
        $this->id = $id;
        $this->type = $type;
        $this->count = $count;
//        $this->product = $product;
    }

    public function jsonSerialize()
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'count' => $this->count,
//            'p' => $this->product,
        ];
    }

    public function is(string $type): bool
    {
        return $this->type == $type;
    }
}
