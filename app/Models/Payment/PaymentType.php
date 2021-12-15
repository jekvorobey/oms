<?php

namespace App\Models\Payment;

/**
 * Class PaymentType
 * Типы оплаты, возвращаемые из Юкассы
 *
 * @package App\Models\Payment
 */
class PaymentType
{
    public const SBERBANK = 'sberbank';
    public const B2B_SBERBANK = 'b2b_sberbank';
    public const MOBILE_BALANCE = 'mobile_balance';
    public const CASH = 'cash';
    //TODO::Добавить все типы оплаты

    /**
     * Получить типы оплаты, не поддерживающие частичный возврат заказа
     * @return string[]
     */
    public static function typesWithoutPartiallyCancel(): array
    {
        return [self::B2B_SBERBANK, self::MOBILE_BALANCE, self::CASH];
    }
}
