<?php

namespace App\Models\Order;

use Greensight\CommonMsa\Models\AbstractModel;

/**
 * * Класс-модель для справочника Причины отмены заказа
 *
 * @property int $id - id
 * @property string $text - причина отмены
 */
class OrderReturnReason extends AbstractModel
{
    public const FILLABLE = ['text'];

    protected $fillable = self::FILLABLE;
}
