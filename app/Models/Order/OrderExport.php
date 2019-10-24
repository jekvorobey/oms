<?php

namespace App\Models\Order;

use Greensight\CommonMsa\Models\AbstractModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Класс-модель для сущности "Информация о заказах во внешних системах"
 * Class OrderExport
 * @package App\Models\Order
 *
 * @property int $order_id - id заказа
 * @property int $merchant_integration_id - id интеграции мерчанта со внешней системой
 * @property string $order_xml_id - id заказа во внешней системе
 *
 * @property Order $order - заказ
 */
class OrderExport extends AbstractModel
{
    /**
     * Заполняемые поля модели
     */
    const FILLABLE = ['order_id', 'merchant_integration_id',  'order_xml_id'];
    
    /**
     * @var array
     */
    protected $fillable = self::FILLABLE;
    
    /**
     * @var string
     */
    protected $table = 'orders_export';
    
    /**
     * @var array
     */
    protected static $restIncludes = ['order'];
    
    /**
     * @return BelongsTo
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
