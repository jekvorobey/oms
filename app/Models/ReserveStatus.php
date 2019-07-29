<?php

namespace App\Models;

/**
 * Статус резерва
 * Class ReserveStatus
 * @package App\Models
 */
class ReserveStatus
{
    /**
     * @return array
     */
    public static function validValues(): array
    {
        return [
            1, //В наличии
            2, //Отсутствует
        ];
    }
}
