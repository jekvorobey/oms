<?php

namespace App\Models;

/**
 * Статус заказа
 * Class OrderStatus
 * @package App\Models
 */
class OrderStatus
{
    /**
     * @return array
     */
    public static function validValues(): array
    {
        return [
            1, //Новый
            2, //В обработке
            3, //Завершен
        ];
    }
}
