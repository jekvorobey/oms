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
    //TODO::Добавить все типы оплаты

    /**
     * Получить типы оплаты, не поддерживающие частичный возврат заказа
     * @return string[]
     */
    public static function typesWithoutPartiallyCancel(): array
    {
        return [self::SBERBANK];
    }
}
