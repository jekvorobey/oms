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
 * @property array $settings - Дополнительные настройки оплаты
 */
class PaymentMethod extends AbstractModel
{
    public const PREPAID = 'prepaid';
    public const POSTPAID = 'postpaid';
    public const CREDITPAID = 'creditpaid';

    protected $fillable = [
        'name',
        'code',
        'active',
        'is_postpaid',
        'settings',
    ];

    protected $casts = [
        'active' => 'bool',
        'is_postpaid' => 'bool',
        'settings' => 'json',
    ];
}
