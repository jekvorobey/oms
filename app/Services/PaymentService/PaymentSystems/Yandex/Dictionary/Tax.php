<?php

namespace App\Services\PaymentService\PaymentSystems\Yandex\Dictionary;

/**
 * Справочник доступных систем налогообложения
 *
 * @package App\Services\PaymentService\PaymentSystems\Yandex\Dictionary
 */
class Tax
{
    /** Общая система налогообложения */
    public const TAX_SYSTEM_CODE_BASE = 1;

    /** Упрощенная (УСН, доходы) */
    public const TAX_SYSTEM_CODE_SIMPLE = 2;

    /** Упрощенная (УСН, доходы минус расходы) */
    public const TAX_SYSTEM_CODE_SIMPLE_MINUS_INCOME = 3;

    /** Единый налог на вмененный доход (ЕНВД) */
    public const TAX_SYSTEM_CODE_ENVD = 4;

    /** Единый сельскохозяйственный налог (ЕСН) */
    public const TAX_SYSTEM_CODE_ESN = 5;

    /** Патентная система налогообложения */
    public const TAX_SYSTEM_CODE_PATENT = 6;

    protected static array $validValues = [
        self::TAX_SYSTEM_CODE_BASE => true,
        self::TAX_SYSTEM_CODE_SIMPLE => true,
        self::TAX_SYSTEM_CODE_SIMPLE_MINUS_INCOME => true,
        self::TAX_SYSTEM_CODE_ENVD => true,
        self::TAX_SYSTEM_CODE_ESN => true,
        self::TAX_SYSTEM_CODE_PATENT => true,
    ];
}
