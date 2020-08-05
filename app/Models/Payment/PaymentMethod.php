<?php

namespace App\Models\Payment;

use App\Models\OmsModel;

/**
 * Class PaymentMethod
 * @package App\Models\Payment
 *
 * @property string $name - Название способа оплаты
 * @property string $code - Символьный код способа оплаты
 * @property int|bool $accept_prepaid - Поддержка предоплаченных банковских карт
 * @property int|bool $accept_virtual - Поддержка виртуальных банковских карт
 * @property int|bool $accept_real - Поддержка пластиковых банковских карт
 * @property int|bool $accept_postpaid - Поддержка дебетовых и кредитных банковских карт
 * @property float $covers - Доля от суммы, которую можно оплатить выбранным способом
 * @property float $max_limit - Максимальная сумма оплаты за одну операцию
 * @property string|array|null $excluded_payment_methods - Не сочетается с указанными методами
 * @property string|array|null $excluded_regions - Недоступен в указанных регионах
 * @property string|array|null $excluded_delivery_services - Недоступен для указанных Л.О.
 * @property string|array|null $excluded_offer_statuses - Недоступен для офферов с указанными статусами
 * @property string|array|null $excluded_customers - Недоступен для указанных пользователей
 * @property int|bool $active - Статус метода оплаты
 */
class PaymentMethod extends OmsModel
{
    /** @deprecated */
    /** @var int - онлайн */
    public const ONLINE = 1;

    /**
     * @return array
     */
    public static function validValues(): array
    {
        return [
            self::ONLINE,
        ];
    }

}
