<?php

namespace App\Models;

use Greensight\CommonMsa\Models\AbstractModel;

/**
 * Класс-модель для сущности "Корзина"
 * Class Basket
 * @package App\Models
 *
 * @property int $customer_id - id покупателя
 * @property int $order_id - id заказа
 */
class Basket extends AbstractModel
{
    /**
     * Заполняемые поля модели
     */
    const FILLABLE = ['customer_id', 'order_id'];
    
    /**
     * @var array
     */
    protected $fillable = self::FILLABLE;
}
