<?php

namespace App\Models\Delivery;

use Greensight\CommonMsa\Models\AbstractModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Класс-модель для сущности "Информация об отправлениях во внешних системах"
 * Class OrderExport
 * @package App\Models\Order
 *
 * @property int $shipment_id  id отправления
 * @property int $merchant_integration_id  id интеграции мерчанта со внешней системой
 * @property string $shipment_xml_id  id отправления во внешней системе
 * @property int $err_code  код ошибки
 * @property string $err_message  сообщение ошибки
 *
 * @property Shipment $shipment - заказ
 */
class ShipmentExport extends AbstractModel
{
    /**
     * Заполняемые поля модели
     */
    const FILLABLE = ['shipment_id', 'merchant_integration_id',  'shipment_xml_id', 'err_code', 'err_message'];
    
    /**
     * @var array
     */
    protected $fillable = self::FILLABLE;
    
    /**
     * @var string
     */
    protected $table = 'shipment_export';
    
    /**
     * @var array
     */
    protected static $restIncludes = ['shipment'];
    
    /**
     * @return BelongsTo
     */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }
}
