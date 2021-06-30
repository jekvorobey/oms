<?php

if (! function_exists('in_production')) {
    /**
     * Находится ли приложение в прод режиме
     */
    function in_production(): bool
    {
        return app()->environment('production');
    }
}

if (! function_exists('g2kg')) {
    /**
     * Перевести граммы в килограммы
     * @param float $value - значение в граммах
     */
    function g2kg(float $value): float
    {
        return $value / 1000;
    }
}

if (! function_exists('price_format')) {
    /**
     * Вывести число в виде цены
     */
    function price_format(float $value): string
    {
        return str_replace('.00', '', number_format($value, 2, '.', ' '));
    }
}

if (! function_exists('qty_format')) {
    /**
     * Вывести число в виде кол-ва
     */
    function qty_format(float $value): string
    {
        return number_format($value, 0, '.', ' ');
    }
}

if (! function_exists('short_day_of_week')) {
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

        return $days[$dayNumber] ?? '';
    }
}

if (! function_exists('phoneNumberFormat')) {
    /**
     * Приведение номера телефона к формату, принимаемому API ЛО
     */
    function phoneNumberFormat(?string $phoneNumber = null): string
    {
        return $phoneNumber
            ? preg_replace('/[^\d+]+/', '', $phoneNumber)
            : '';
    }
}
