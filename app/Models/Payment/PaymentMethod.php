<?php

namespace App\Models\Payment;

/**
 * Class PaymentMethod
 * @package App\Models\Payment
 */
class PaymentMethod
{
    /** @var int - онлайн */
    public const ONLINE = 1;

    /** @var int */
    public $id;
    /** @var string */
    public $name;

    /**
     * @return array
     */
    public static function all(): array
    {
        return [
            new PaymentMethod(self::ONLINE, "Онлайн")
        ];
    }

    /**
     * @return array
     */
    public static function validValues(): array
    {
        return [
            self::ONLINE,
        ];
    }

    /**
     * PaymentMethod constructor.
     * @param  int  $id
     * @param  string  $name
     */
    public function __construct(int $id, string $name)
    {
        $this->id = $id;
        $this->name = $name;
    }
}
