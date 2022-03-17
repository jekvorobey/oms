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
 * )
 *
 * Class PaymentMethod
 * @package App\Models\Payment
 *
 * @property string $name - Название способа оплаты
 * @property string $code - Символьный код способа оплаты
 * @property bool $active - Статус метода оплаты
 * @property bool $is_postpaid - Признак постоплаты (оплата при получении)
 */
class PaymentMethod extends AbstractModel
{
    protected $fillable = [
        'name',
        'code',
        'active',
        'is_postpaid',
    ];

    protected $casts = [
        'active' => 'bool',
        'is_postpaid' => 'bool',
    ];
}
