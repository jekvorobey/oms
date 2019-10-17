<?php

namespace App\Models\Delivery;

use App\Models\Basket\BasketItem;
use App\Models\OmsModel;
use App\Models\Order\OrderHistoryEvent;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Содержимое коробки отправления
 * Class ShipmentPackageItem
 * @package App\Models\Delivery
 *
 * @property int $shipment_package_id
 * @property int $basket_item_id
 * @property float $reserved_qty
 * @property int $reserved_by
 *
 * @property-read ShipmentPackage $shipmentPackage
 * @property-read BasketItem $basketItem
 */
class ShipmentPackageItem extends OmsModel
{
    /** @var string */
    protected $table = 'shipment_packages_items';
    
    /**
     * @return BelongsTo
     */
    public function shipmentPackage(): BelongsTo
    {
        return $this->belongsTo(ShipmentPackage::class);
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
        
        self::created(function (self $shipmentPackageItem) {
            OrderHistoryEvent::saveEvent(
                OrderHistoryEvent::TYPE_CREATE,
                $shipmentPackageItem->shipmentPackage->shipment->delivery->order_id,
                $shipmentPackageItem
            );
        });
    
        self::updated(function (self $shipmentPackageItem) {
            OrderHistoryEvent::saveEvent(
                OrderHistoryEvent::TYPE_UPDATE,
                $shipmentPackageItem->shipmentPackage->shipment->delivery->order_id,
                $shipmentPackageItem
            );
        });
    
        self::deleting(function (self $shipmentPackageItem) {
            OrderHistoryEvent::saveEvent(
                OrderHistoryEvent::TYPE_DELETE,
                $shipmentPackageItem->shipmentPackage->shipment->delivery->order_id,
                $shipmentPackageItem
            );
        });
    }
}
