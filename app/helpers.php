<?php

use Doctrine\DBAL\Query\QueryBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

if (! function_exists('in_production')) {
    /**
     * Находится ли приложение в прод режиме
     */
    function in_production(): bool
    {
        return app()->environment('production');
    }
}

if (! function_exists('in_prod_stage')) {
    /**
     * Находится ли приложение на прод стенде
     * отличается от in_production, т.к. environment=production так же и в настройках stage-стенда
     */
    function in_prod_stage(): bool
    {
        return config('app.stage') === 'prod';
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

if (!function_exists('query_builder_to_sql')) {
    /** @param Builder|QueryBuilder|Relation $query */
    function query_builder_to_sql($query): ?string
    {
        return config('app.debug')
            ? vsprintf(str_replace('?', '%s', $query->toSql()), collect($query->getBindings())->map(function ($binding) {
                $binding = addslashes($binding);
                return is_numeric($binding) ? $binding : "'{$binding}'";
            })->toArray())
            : null;
    }
}
