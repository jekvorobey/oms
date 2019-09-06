<?php

namespace App\Models\Payment;

class PaymentStatus
{
    public const CREATED = 1;
    public const STARTED = 2;
    public const TIMEOUT = 3;
    public const CANCELED = 4;
    public const PARTIAL_DONE = 5;
    public const DONE = 6;
    
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
            new self(self::CREATED, 'Создана'),
            new self(self::STARTED, 'Начата'),
            new self(self::TIMEOUT, 'Время истекло'),
            new self(self::CANCELED, 'Отменена'),
            new self(self::PARTIAL_DONE, 'Частично оплачено'),
            new self(self::DONE, 'Оплачено'),
        ];
    }
    
    public function __construct(int $id, string $name)
    {
        $this->id = $id;
        $this->name = $name;
    }
}
