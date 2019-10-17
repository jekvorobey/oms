<?php

namespace App\Models\Delivery;

use App\Models\Basket\BasketItem;
use App\Models\OmsModel;
use App\Models\Order\OrderHistoryEvent;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Состав отправления (набор товаров с одного склада одного мерчанта)
 * Class ShipmentItem
 * @package App\Models\Delivery
 *
 * @property int $shipment_id
 * @property int $basket_item_id
 *
 * @property-read Shipment $shipment
 * @property-read BasketItem $basketItem
 */
class ShipmentItem extends OmsModel
{
    /** @var string */
    protected $table = 'shipment_items';
    
    /**
     * @return BelongsTo
     */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }
    
    /**
     * @return BelongsTo
     */
    public function basketItem(): BelongsTo
    {
        return $this->belongsTo(BasketItem::class);
    }
    
    protected static function boot()
    {
        parent::boot();
        
        self::created(function (self $shipmentItem) {
            OrderHistoryEvent::saveEvent(OrderHistoryEvent::TYPE_CREATE, $shipmentItem->shipment->delivery->order_id, $shipmentItem);
        });
    
        self::updated(function (self $shipmentItem) {
            OrderHistoryEvent::saveEvent(OrderHistoryEvent::TYPE_UPDATE, $shipmentItem->shipment->delivery->order_id, $shipmentItem);
        });
    
        self::deleting(function (self $shipmentItem) {
            OrderHistoryEvent::saveEvent(OrderHistoryEvent::TYPE_DELETE, $shipmentItem->shipment->delivery->order_id, $shipmentItem);
        });
    }
}
