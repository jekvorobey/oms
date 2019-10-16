<?php

namespace App\Models\Payment;

/**
 * Class PaymentStatus
 * @package App\Models\Payment
 */
class PaymentStatus
{
    /** @var int - создана */
    public const STATUS_CREATED = 1;
    /** @var int - начата */
    public const STATUS_STARTED = 2;
    /** @var int - время истекло */
    public const STATUS_TIMEOUT = 3;
    /** @var int - отменена */
    public const STATUS_CANCELED = 4;
    /** @var int - частично оплачено */
    public const STATUS_PARTIAL_DONE = 5;
    /** @var int - оплачено */
    public const STATUS_DONE = 6;
    
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
            new self(self::STATUS_CREATED, 'Создана'),
            new self(self::STATUS_STARTED, 'Начата'),
            new self(self::STATUS_TIMEOUT, 'Время истекло'),
            new self(self::STATUS_CANCELED, 'Отменена'),
            new self(self::STATUS_PARTIAL_DONE, 'Частично оплачено'),
            new self(self::STATUS_DONE, 'Оплачено'),
        ];
    }
    
    /**
     * @return array
     */
    public static function validValues()
    {
        return [
            self::STATUS_CREATED,
            self::STATUS_STARTED,
            self::STATUS_TIMEOUT,
            self::STATUS_CANCELED,
            self::STATUS_PARTIAL_DONE,
            self::STATUS_DONE
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
