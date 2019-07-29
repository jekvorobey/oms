<?php

namespace App\Models;

use Greensight\CommonMsa\Models\AbstractModel;
use Illuminate\Support\Carbon;

/**
 * Класс-модель для сущности "Элемент корзины"
 * Class BasketItem
 * @package App\Models
 *
 * @property int $basket_id - id корзины
 * @property int $offer_id - id предложения мерчанта
 * @property string $name - название товара
 * @property float $qty - кол-во
 * @property float $price - цена за единицу
 * @property bool $is_reserved - товар зарезервирован?
 * @property int $reserved_by - кем зарезервирован
 * @property Carbon $reserved_at - когда зарезервирован
 */
class BasketItem extends AbstractModel
{
    /**
     * Заполняемые поля модели
     */
    const FILLABLE = ['basket_id', 'offer_id', 'name', 'price', 'is_reserved', 'reserved_by', 'reserved_at'];
    
    /**
     * @var array
     */
    protected $fillable = self::FILLABLE;
}
