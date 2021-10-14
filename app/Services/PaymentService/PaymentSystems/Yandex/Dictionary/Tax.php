<?php

namespace App\Services\PaymentService\PaymentSystems\Yandex\Dictionary;

use YooKassa\Common\AbstractEnum;

/**
 * Справочник доступных систем налогообложения
 *
 * @package App\Services\PaymentService\PaymentSystems\Yandex\Dictionary
 */
class Tax extends AbstractEnum
{
    /** Общая система налогообложения */
    public const BASE = 1;

    /** Упрощенная (УСН, доходы) */
    public const SIMPLE = 2;

    /** Упрощенная (УСН, доходы минус расходы) */
    public const SIMPLE_MINUS_INCOME = 3;

    /** Единый налог на вмененный доход (ЕНВД) */
    public const ENVD = 4;

    /** Единый сельскохозяйственный налог (ЕСН) */
    public const ESN = 5;

    /** Патентная система налогообложения */
    public const PATENT = 6;

    protected static $validValues = [
        self::BASE => true,
        self::SIMPLE => true,
        self::SIMPLE_MINUS_INCOME => true,
        self::ENVD => true,
        self::ESN => true,
        self::PATENT => true,
    ];
}
