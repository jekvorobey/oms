<?php

namespace App\Models\Payment;

/**
 * Платежная система
 * Class PaymentSystem
 * @package App\Models\Payment
 */
class PaymentSystem
{
    /** @var int - Яндекс.Касса */
    public const YANDEX = 1;
    /** @var int - тестовая система */
    public const TEST = 42;
    
    /** @var int */
    public $id;
    /** @var string */
    public $name;
    
    /**
     * @return array|PaymentStatus[]
     */
    public static function all(): array
    {
        return [
            new self(self::YANDEX, 'Яндекс.Касса'),
            new self(self::TEST, 'Тестовая система оплаты'),
        ];
    }


    /**
     * @return array
     */
    public static function validValues(): array
    {
        return [
            self::YANDEX,
            self::TEST,
        ];
    }

    /**
     * PaymentSystem constructor.
     * @param  int  $id
     * @param  string  $name
     */
    public function __construct(int $id, string $name)
    {
        $this->id = $id;
        $this->name = $name;
    }
}
