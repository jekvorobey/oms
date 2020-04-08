<?php

use App\Models\City;
use App\Models\Region;
use App\Services\External\DaData\DaDataService;

if (! function_exists('in_production')) {

    /**
     * Находится ли приложение в прод режиме
     * @return boolean
     */
    function in_production(): bool
    {
        return app()->environment('production');
    }
}

if (! function_exists('g2kg')) {

    /**
     * Перевести граммы в килограммы
     * @param  float  $value - значение в граммах
     * @return float
     */
    function g2kg(float $value): float
    {
        return $value / 1000;
    }
}

if (! function_exists('price_format')) {

    /**
     * Вывести число в виде цены
     * @param  float  $value
     * @return string
     */
    function price_format(float $value): string
    {
        return number_format($value, 2, '.', ' ');
    }
}

if (! function_exists('qty_format')) {

    /**
     * Вывести число в виде кол-ва
     * @param  float  $value
     * @return string
     */
    function qty_format(float $value): string
    {
        return number_format($value, 0, '.', ' ');
    }
}
