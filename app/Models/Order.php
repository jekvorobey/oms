<?php

namespace App\Models;

use Greensight\CommonMsa\Models\AbstractModel;
use Illuminate\Support\Carbon;

/**
 * Класс-модель для сущности "Заказы"
 * Class Order
 * @package App\Models
 *
 * @property int $customer_id - id покупателя
 * @property string $number - номер
 * @property float $cost - стоимость
 * @property int $status - статус
 * @property int $reserve_status - статус резерва
 * @property int $delivery_type - тип доставки (одним отправлением, несколькими отправлениями)
 * @property int $delivery_method - способ доставки
 * @property Carbon $processing_time - срок обработки (укомплектовки)
 * @property Carbon $delivery_time - срок доставки
 * @property string $comment - комментарий
 */
class Order extends AbstractModel
{
    /**
     * Заполняемые поля модели
     */
    const FILLABLE = ['customer_id', 'number', 'cost', 'status', 'reserve_status', 'delivery_type', 'delivery_method', 'processing_time', 'delivery_time', 'comment'];
    
    /**
     * @var array
     */
    protected $fillable = self::FILLABLE;
}
