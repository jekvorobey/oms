<?php

namespace App\Services\PaymentService\PaymentSystems\Yandex\Dictionary;

use YooKassa\Common\AbstractEnum;

/**
 * Справочник доступных ставок НДС
 *
 * @package App\Services\PaymentService\PaymentSystems\Yandex\Dictionary
 */
class VatCode extends AbstractEnum
{
    public const CODE_DEFAULT = 1;
    public const CODE_0_PERCENT = 2;
    public const CODE_10_PERCENT = 3;
    public const CODE_20_PERCENT = 4;

    protected static $validValues = [
        self::CODE_DEFAULT => true,
        self::CODE_0_PERCENT => true,
        self::CODE_10_PERCENT => true,
        self::CODE_20_PERCENT => true,
    ];
}
