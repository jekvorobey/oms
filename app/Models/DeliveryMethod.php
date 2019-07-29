<?php

namespace App\Models;

/**
 * Способ доставки
 * Class DeliveryMethod
 * @package App\Models
 */
class DeliveryMethod
{
    /**
     * @return array
     */
    public static function validValues(): array
    {
        return [
            1, //Самовывоз
            2, //Доставка
        ];
    }
}
