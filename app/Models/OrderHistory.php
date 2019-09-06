<?php

namespace App\Models;

use Greensight\CommonMsa\Models\AbstractModel;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Класс-модель для сущности "история Заказов"
 * Class OrderHistory
 * @package App\Models
 *
 * @property int $order_id - id заказа
 * @property int $user_id - id пользователя
 * @property int $type - тип события
 * @property string $data - информация
 * @property int $entity_id
 * @property int $entity
 *
 * @property Order $order - заказ
 */
class OrderHistory extends AbstractModel
{
    /**
     * Заполняемые поля модели
     */
    const FILLABLE = ['order_id', 'user_id', 'data', 'entity_id', 'entity'];

    /**
     * @var array
     */
    protected $fillable = self::FILLABLE;

    /**
     * @return HasOne
     */
    public function order(): HasOne
    {
        return $this->hasOne(Order::class, 'id', 'order_id');
    }

    protected $table = 'orders_history';
}
