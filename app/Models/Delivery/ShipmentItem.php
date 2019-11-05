<?php

namespace App\Models\Delivery;

use App\Models\Basket\BasketItem;
use App\Models\History\HistoryType;
use App\Models\OmsModel;
use App\Models\History\History;
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
     * @var array
     */
    protected static $restIncludes = ['shipment', 'basketItem'];
    
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
    
        self::saved(function (self $shipmentItem) {
            $shipmentItem->shipment->costRecalc();
        });
    
        self::deleted(function (self $shipmentItem) {
            $shipmentItem->shipment->costRecalc();
        });
    
        self::created(function (self $shipmentItem) {
            History::saveEvent(
                HistoryType::TYPE_CREATE,
                [
                    $shipmentItem->shipment->delivery->order,
                    $shipmentItem->shipment,
                ],
                $shipmentItem
            );
        });
    
        self::updated(function (self $shipmentItem) {
            History::saveEvent(
                HistoryType::TYPE_UPDATE,
                [
                    $shipmentItem->shipment->delivery->order,
                    $shipmentItem->shipment,
                ],
                $shipmentItem
            );
        });
    
        self::deleting(function (self $shipmentItem) {
            History::saveEvent(
                HistoryType::TYPE_DELETE,
                [
                    $shipmentItem->shipment->delivery->order,
                    $shipmentItem->shipment,
                ],
                $shipmentItem
            );
        });
    }
}
