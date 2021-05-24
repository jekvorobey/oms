<?php

namespace App\Models\Payment;

/**
 * Class PaymentStatus
 * @package App\Models\Payment
 */
class PaymentStatus
{
    /** не оплачена */
    public const NOT_PAID = 1;
    /** оплачена */
    public const PAID = 2;
    /** просрочена */
    public const TIMEOUT = 3;
    /** средства захолдированы */
    public const HOLD = 4;
    /** ошибка */
    public const ERROR = 5;
    /** ожидает оплаты */
    public const WAITING = 6;

    /** @var int */
    public $id;
    /** @var string */
    public $name;

    /**
     * PaymentStatus constructor.
     */
    public function __construct(int $id, string $name)
    {
        $this->id = $id;
        $this->name = $name;
    }

    /**
     * @return array
     */
    public static function all()
    {
        return [
            new self(self::NOT_PAID, 'Не оплачено'),
            new self(self::PAID, 'Оплачено'),
            new self(self::TIMEOUT, 'Просрочено'),
            new self(self::HOLD, 'Средства захолдированы'),
            new self(self::ERROR, 'Ошибка проведения платежа'),
            new self(self::WAITING, 'Ожидает оплаты'),
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
            self::HOLD,
            self::ERROR,
            self::WAITING,
        ];
    }
}
