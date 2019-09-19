<?php

namespace App\Models\Delivery;

/**
 * Тип доставки
 * Class DeliveryType
 * @package App\Models
 */
class DeliveryType
{
    /**
     * @return array
     */
    public static function validValues(): array
    {
        return [
            1, //Одним отправлением
            2, //Несколькими отправлениями
        ];
    }
}
