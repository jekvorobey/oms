<?php

namespace App\Models\Order;

/**
 * Статус заказа
 * Class OrderStatus
 * @package App\Models
 */
class OrderStatus
{
    /** @var int - предзаказ: ожидаем поступления товара */
    public const PRE_ORDER = 0;
    /** @var int - оформлен */
    public const CREATED = 1;
    /** @var int - ожидает проверки АОЗ */
    public const AWAITING_CHECK = 2;
    /** @var int - проверка АОЗ */
    public const CHECKING = 3;
    /** @var int - ожидает подтверждения Мерчантом */
    public const AWAITING_CONFIRMATION = 4;
    /** @var int - в обработке */
    public const IN_PROCESSING = 5;
    /** @var int - передан на доставку */
    public const TRANSFERRED_TO_DELIVERY = 6;
    /** @var int - в процессе доставки */
    public const DELIVERING = 7;
    /** @var int - находится в Пункте Выдачи */
    public const READY_FOR_RECIPIENT = 8;
    /** @var int - доставлен */
    public const DONE = 9;
    /** @var int - возвращен */
    public const RETURNED = 10;

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
            self::CREATED => new self(
                self::CREATED,
                'Оформлен'
            ),
            self::AWAITING_CHECK => new self(
                self::AWAITING_CHECK,
                'Ожидает проверки АОЗ'
            ),
            self::CHECKING => new self(
                self::CHECKING,
                'Проверка АОЗ'
            ),
            self::AWAITING_CONFIRMATION => new self(
                self::AWAITING_CONFIRMATION,
                'Ожидает подтверждения Мерчантом'
            ),
            self::IN_PROCESSING => new self(
                self::IN_PROCESSING,
                'В обработке'
            ),
            self::TRANSFERRED_TO_DELIVERY => new self(
                self::TRANSFERRED_TO_DELIVERY,
                'Передан на доставку'
            ),
            self::DELIVERING => new self(
                self::DELIVERING,
                'В процессе доставки'
            ),
            self::READY_FOR_RECIPIENT => new self(
                self::READY_FOR_RECIPIENT,
                'Находится в Пункте Выдачи'
            ),
            self::DONE => new self(
                self::DONE,
                'Доставлен'
            ),
            self::RETURNED => new self(
                self::RETURNED,
                'Возвращен'
            ),
            self::PRE_ORDER => new self(
                self::PRE_ORDER,
                'Предзаказ: ожидаем поступления товара'
            ),
        ];
    }

    /**
     * @return array
     */
    public static function validValues(): array
    {
        return array_keys(static::all());
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
