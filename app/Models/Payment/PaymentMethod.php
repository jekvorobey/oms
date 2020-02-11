<?php

namespace App\Models\Payment;

class PaymentMethod
{
    public const TYPE_ONLINE = 1;

    /** @var int */
    public $id;
    /** @var string */
    public $name;

    public static function all(): array
    {
        return [
            new PaymentMethod(self::TYPE_ONLINE, "Онлайн")
        ];
    }

    public function __construct(int $id, string $name)
    {
        $this->id = $id;
        $this->name = $name;
    }
}
