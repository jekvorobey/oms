<?php

namespace App\Models\Order;

/**
 * Статус заказа
 * Class OrderStatus
 * @package App\Models
 */
class OrderStatus
{
    public const CREATED = 1;
    public const PROCESS = 2;
    public const DONE = 3;
    public const CANCEL = 4;
    /**
     * @return array
     */
    public static function validValues(): array
    {
        return [
            self::CREATED,
            self::PROCESS,
            self::DONE,
            self::CANCEL,
        ];
    }
}
