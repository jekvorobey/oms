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
        return str_replace('.00', '', number_format($value, 2, '.', ' '));
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

if (! function_exists('short_day_of_week')) {

    /**
     * @param  int  $dayNumber
     * @return string
     */
    function short_day_of_week(int $dayNumber): string
    {
        $days = [
            'вс',
            'пн',
            'вт',
            'ср',
            'чт',
            'пт',
            'сб',
        ];

        return isset($days[$dayNumber]) ? $days[$dayNumber] : '';
    }
}

if (! function_exists('xml_entities')) {
    /**
     * Метод для замены XML escape characters
     * (используется при создании документов Microsoft Office)
     *
     * @param $string
     * @return string
     */
    function xml_entities($string)
    {
        return strtr(
            $string,
            array(
                "<" => "&lt;",
                ">" => "&gt;",
                '"' => "&quot;",
                "'" => "&apos;",
                "&" => "&amp;",
            )
        );
    }
}
