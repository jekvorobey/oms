<?php

namespace App\Models\Payment;

/**
 * Class PaymentStatus
 * @package App\Models\Payment
 */
class PaymentStatus
{
    /** @var int - не оплачена */
    public const NOT_PAID = 1;
    /** @var int - оплачена */
    public const PAID = 2;
    /** @var int - просрочена */
    public const TIMEOUT = 3;
    
    /** @var int */
    public $id;
    /** @var string */
    public $name;
    
    /**
     * @return array
     */
    public static function all()
    {
        return [
            new self(self::NOT_PAID, 'Не оплачено'),
            new self(self::PAID, 'Оплачено'),
            new self(self::TIMEOUT, 'Просрочено'),
        ];
    }
    
    /**
     * @return array
     */
    public static function validValues(): array
    {
        return [
            self::NOT_PAID,
            self::PAID,
            self::TIMEOUT,
        ];
    }
    
    /**
     * PaymentStatus constructor.
     * @param  int  $id
     * @param  string  $name
     */
    public function __construct(int $id, string $name)
    {
        $this->id = $id;
        $this->name = $name;
    }
}
