<?php

namespace App\Models\Payment;

/**
 * Платежная система
 * Class PaymentSystem
 * @package App\Models\Payment
 */
class PaymentSystem
{
    public const TEST = 42;
    
    /** @var int */
    public $id;
    /** @var string */
    public $name;
    
    /**
     * @return array|PaymentStatus[]
     */
    public static function all()
    {
        return [
            new self(self::TEST, 'Тестовая система оплаты'),
        ];
    }
    
    public function __construct(int $id, string $name)
    {
        $this->id = $id;
        $this->name = $name;
    }
}
