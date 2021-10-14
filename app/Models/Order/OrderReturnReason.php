<?php

namespace App\Models\Order;

use Illuminate\Database\Eloquent\Model;

/**
 * * Класс-модель для справочника Причины отмены заказа
 *
 * @property int $id - id
 * @property string $text - причина отмены
 */
class OrderReturnReason extends Model
{
    public const FILLABLE = ['text'];

    protected $fillable = self::FILLABLE;
}
