<?php

namespace App\Models\Order;

/**
 * Статус заказа
 * Class OrderStatus
 * @package App\Models
 */
class OrderStatus
{
    /** @var int - создан */
    public const STATUS_CREATED = 1;
    /** @var int - в обработке */
    public const STATUS_PROCESS = 2;
    /** @var int - выполнен */
    public const STATUS_DONE = 3;
    /** @var int - отменен */
    public const STATUS_CANCEL = 4;
    
    /**
     * @return array
     */
    public static function validValues(): array
    {
        return [
            self::STATUS_CREATED,
            self::STATUS_PROCESS,
            self::STATUS_DONE,
            self::STATUS_CANCEL,
        ];
    }
}
