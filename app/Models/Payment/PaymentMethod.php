<?php

namespace App\Models\Payment;

use Greensight\CommonMsa\Models\AbstractModel;

/**
 * @OA\Schema(
 *     description="Способы платежей",
 *     @OA\Property(property="name", type="string", description="Название способа оплаты"),
 *     @OA\Property(property="code", type="string", description="Символьный код способа оплаты"),
 *     @OA\Property(property="active", type="boolean", description="Статус метода оплаты"),
 *     @OA\Property(property="is_postpaid", type="boolean", description="Признак постоплаты (оплата при получении)"),
 *     @OA\Property(property="is_need_create_payment", type="boolean", description="Признак необходимости создания заказа в платежной системе"),
 *     @OA\Property(property="is_apply_discounts", type="boolean", description="Признак возможности применения скидок"),
 *     @OA\Property(property="is_available_for_mc", type="boolean", description="Признак возможности оплаты МК"),
 *     @OA\Property(property="button_text", type="string", description="Текст на кнопке (с тэгами)"),
 *     @OA\Property(property="min_available_price", type="number", description="Доступность варианта оплаты при сумме от"),
 *     @OA\Property(property="max_available_price", type="number", description="Доступность варианта оплаты при сумме до"),
 *     @OA\Property(property="settings", type="array", description="Дополнительные настройки оплаты"),
 * )
 *
 * Class PaymentMethod
 * @package App\Models\Payment
 *
 * @property string $name - Название способа оплаты
 * @property string $code - Символьный код способа оплаты
 * @property bool $active - Статус метода оплаты
 * @property bool $is_postpaid - Признак постоплаты (оплата при получении)
 * @property bool $is_need_create_payment - Признак необходимости создания заказа в платежной системе
 * @property bool $is_apply_discounts - Признак возможности применения скидок
 * @property bool $is_available_for_mc - Признак возможности оплаты МК
 * @property string $button_text - Текст на кнопке (с тэгами)
 * @property float $min_available_price - Доступность варианта оплаты при сумме от
 * @property float $max_available_price - Доступность варианта оплаты при сумме до
 * @property array $settings - Дополнительные настройки оплаты
 */
class PaymentMethod extends AbstractModel
{
    public const PREPAID = 1;
    public const POSTPAID = 2;
    public const CREDITPAID = 3;
    public const B2B_SBERBANK = 4;
    public const BANK_TRANSFER_FOR_LEGAL = 5;
    public const SBP_RAIFFEISEN = 6;

    protected $fillable = [
        'name',
        'code',
        'active',
        'is_postpaid',
        'is_need_create_payment',
        'is_apply_discounts',
        'is_available_for_mc',
        'button_text',
        'min_available_price',
        'max_available_price',
        'settings',
    ];

    protected $casts = [
        'active' => 'bool',
        'is_postpaid' => 'bool',
        'is_need_create_payment' => 'bool',
        'is_apply_discounts' => 'bool',
        'is_available_for_mc' => 'bool',
        'settings' => 'json',
    ];

    public static function validValues(): array
    {
        return [
            self::PREPAID,
            self::POSTPAID,
            self::CREDITPAID,
            self::B2B_SBERBANK,
            self::SBP_RAIFFEISEN,
        ];
    }
}
