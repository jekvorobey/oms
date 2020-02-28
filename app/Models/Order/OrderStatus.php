<?php

namespace App\Models\Order;

/**
 * Статус заказа
 * Class OrderStatus
 * @package App\Models
 */
class OrderStatus
{
    /** @var int - оформлен */
    public const CREATED = 1;
    /** @var int - ожидает подтверждения */
    public const AWAITING_CHECK = 2;
    /** @var int - в обработке */
    public const IN_PROCESSING = 3;
    /** @var int - проверка */
    public const CHECKING = 4;
    /** @var int - передан на доставку */
    public const TRANSFERRED_TO_DELIVERY = 5;
    /** @var int - в процессе доставки */
    public const DELIVERING = 6;
    /** @var int - находится в Пункте Выдачи */
    public const READY_FOR_RECIPIENT = 7;
    /** @var int - доставлен */
    public const DONE = 8;
    /** @var int - возвращен */
    public const RETURNED = 9;
    /** @var int - предзаказ: ожидаем поступления товара */
    public const PRE_ORDER = 10;

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
            new self(self::CREATED, 'Оформлен'),
            new self(self::AWAITING_CHECK, 'Ожидает подтверждения'),
            new self(self::IN_PROCESSING, 'В обработке'),
            new self(self::CHECKING, 'Проверка'),
            new self(self::TRANSFERRED_TO_DELIVERY, 'Передан на доставку'),
            new self(self::DELIVERING, 'В процессе доставки'),
            new self(self::READY_FOR_RECIPIENT, 'Находится в Пункте Выдачи'),
            new self(self::DONE, 'Доставлен'),
            new self(self::RETURNED, 'Возвращен'),
            new self(self::PRE_ORDER, 'Предзаказ: ожидаем поступления товара'),
        ];
    }

    /**
     * @return array
     */
    public static function validValues(): array
    {
        return [
            self::CREATED,
            self::AWAITING_CHECK,
            self::IN_PROCESSING,
            self::CHECKING,
            self::TRANSFERRED_TO_DELIVERY,
            self::DELIVERING,
            self::READY_FOR_RECIPIENT,
            self::DONE,
            self::RETURNED,
            self::PRE_ORDER,
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
