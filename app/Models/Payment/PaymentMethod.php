<?php

namespace App\Models\Payment;

use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     description="Способы платежей",
 *     @OA\Property(property="name", type="string", description="Название способа оплаты"),
 *     @OA\Property(property="code", type="string", description="Символьный код способа оплаты"),
 *     @OA\Property(property="accept_prepaid", type="boolean", description="Поддержка предоплаченных банковских карт"),
 *     @OA\Property(property="accept_virtual", type="boolean", description="Поддержка виртуальных банковских карт"),
 *     @OA\Property(property="accept_real", type="boolean", description="Поддержка пластиковых банковских карт"),
 *     @OA\Property(property="accept_postpaid", type="boolean", description="Поддержка дебетовых и кредитных банковских карт"),
 *     @OA\Property(property="covers", type="number", description="Доля от суммы, которую можно оплатить выбранным способом"),
 *     @OA\Property(property="max_limit", type="number", description="Максимальная сумма оплаты за одну операцию"),
 *     @OA\Property(property="excluded_payment_methods", type="string", description="Не сочетается с указанными методами", example="{}"),
 *     @OA\Property(property="excluded_regions", type="string", description="Недоступен в указанных регионах", example="{}"),
 *     @OA\Property(property="excluded_delivery_services", type="string", description="Недоступен для указанных Л.О.", example="{}"),
 *     @OA\Property(property="excluded_offer_statuses", type="string", description="Недоступен для офферов с указанными статусами", example="{}"),
 *     @OA\Property(property="excluded_customers", type="string", description="Недоступен для указанных пользователей", example="{}"),
 *     @OA\Property(property="active", type="boolean", description="Статус метода оплаты"),
 * )
 *
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
class PaymentMethod extends Model
{
    /** @deprecated ? */
    public const ONLINE = 1;

    /** @var bool */
    protected static $unguarded = true;

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
