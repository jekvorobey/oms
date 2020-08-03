<?php

namespace App\Models\Order;

use App\Models\Basket\Basket;

/**
 * Статус заказа
 * Class OrderStatus
 * @package App\Models\Order
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
    /** @var int[] - типы заказов, для которых актуален статус */
    public $types;

    /**
     * @return array
     */
    public static function all()
    {
        return [
            self::CREATED => new self(
                self::CREATED,
                'Оформлен',
                [
                    Basket::TYPE_PRODUCT,
                    Basket::TYPE_MASTER,
                ]
            ),
            self::AWAITING_CHECK => new self(
                self::AWAITING_CHECK,
                'Ожидает проверки АОЗ',
                [
                    Basket::TYPE_PRODUCT,
                    Basket::TYPE_MASTER,
                ]
            ),
            self::CHECKING => new self(
                self::CHECKING,
                'Проверка АОЗ',
                [
                    Basket::TYPE_PRODUCT,
                    Basket::TYPE_MASTER,
                ]
            ),
            self::AWAITING_CONFIRMATION => new self(
                self::AWAITING_CONFIRMATION,
                'Ожидает подтверждения Мерчантом',
                [
                    Basket::TYPE_PRODUCT,
                ]
            ),
            self::IN_PROCESSING => new self(
                self::IN_PROCESSING,
                'В обработке',
                [
                    Basket::TYPE_PRODUCT,
                ]
            ),
            self::TRANSFERRED_TO_DELIVERY => new self(
                self::TRANSFERRED_TO_DELIVERY,
                'Передан на доставку',
                [
                    Basket::TYPE_PRODUCT,
                ]
            ),
            self::DELIVERING => new self(
                self::DELIVERING,
                'В процессе доставки',
                [
                    Basket::TYPE_PRODUCT,
                ]
            ),
            self::READY_FOR_RECIPIENT => new self(
                self::READY_FOR_RECIPIENT,
                'Находится в Пункте Выдачи',
                [
                    Basket::TYPE_PRODUCT,
                ]
            ),
            self::DONE => new self(
                self::DONE,
                'Доставлен',
                [
                    Basket::TYPE_PRODUCT,
                    Basket::TYPE_MASTER,
                ]
            ),
            self::RETURNED => new self(
                self::RETURNED,
                'Возвращен',
                [
                    Basket::TYPE_PRODUCT,
                    Basket::TYPE_MASTER,
                ]
            ),
            self::PRE_ORDER => new self(
                self::PRE_ORDER,
                'Предзаказ: ожидаем поступления товара',
                [
                    Basket::TYPE_PRODUCT,
                ]
            ),
        ];
    }

    /**
     * @param  int  $type
     * @return array
     */
    public static function validValues(int $type = Basket::TYPE_PRODUCT): array
    {
        return array_keys(array_filter(static::all(), function (self $orderStatus) use ($type) {
            return in_array($type, $orderStatus->types);
        }));
    }

    /**
     * OrderStatus constructor.
     * @param  int  $id
     * @param  string  $name
     * @param  array  $types
     */
    public function __construct(int $id, string $name, array $types)
    {
        $this->id = $id;
        $this->name = $name;
        $this->types = $types;
    }
}
