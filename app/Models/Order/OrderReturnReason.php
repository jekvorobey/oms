<?php

namespace App\Models\Order;

use App\Models\OmsModel;

/**
 * * Класс-модель для справочника Причины отмены заказа
 *
 * @property int $id - id
 * @property string $text - причина отмены
 */
class OrderReturnReason extends OmsModel
{
    public const FILLABLE = ['text'];
    protected $fillable = self::FILLABLE;
}
