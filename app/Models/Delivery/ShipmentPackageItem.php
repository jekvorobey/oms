<?php

namespace App\Models\Delivery;

use App\Models\Basket\BasketItem;
use App\Models\History\HistoryType;
use App\Models\OmsModel;
use App\Models\History\History;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Содержимое коробки отправления
 * Class ShipmentPackageItem
 * @package App\Models\Delivery
 *
 * @property int $shipment_package_id
 * @property int $basket_item_id
 * @property float $qty
 * @property int $set_by
 *
 * @property-read ShipmentPackage $shipmentPackage
 * @property-read BasketItem $basketItem
 */
class ShipmentPackageItem extends OmsModel
{
    /**
     * Заполняемые поля модели
     */
    const FILLABLE = [
        'shipment_package_id',
        'basket_item_id',
        'qty',
        'set_by',
    ];
    
    /**
     * @var array
     */
    protected $fillable = self::FILLABLE;
    
    /** @var string */
    protected $table = 'shipment_package_items';
    
    /**
     * @var array
     */
    protected static $restIncludes = ['shipmentPackage', 'basketItem'];
    
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
            History::saveEvent(
                HistoryType::TYPE_CREATE,
                [
                    $shipmentPackageItem->shipmentPackage->shipment->delivery->order,
                    $shipmentPackageItem->shipmentPackage->shipment,
                ],
                $shipmentPackageItem
            );
        });
    
        self::updated(function (self $shipmentPackageItem) {
            History::saveEvent(
                HistoryType::TYPE_UPDATE,
                [
                    $shipmentPackageItem->shipmentPackage->shipment->delivery->order,
                    $shipmentPackageItem->shipmentPackage->shipment,
                ],
                $shipmentPackageItem
            );
        });
    
        self::deleting(function (self $shipmentPackageItem) {
            History::saveEvent(
                HistoryType::TYPE_DELETE,
                [
                    $shipmentPackageItem->shipmentPackage->shipment->delivery->order,
                    $shipmentPackageItem->shipmentPackage->shipment,
                ],
                $shipmentPackageItem
            );
        });
    
        self::saved(function (self $shipmentPackageItem) {
            if ($shipmentPackageItem->qty != $shipmentPackageItem->getOriginal('qty')) {
                $shipmentPackageItem->shipmentPackage->recalcWeight();
            }
        });
        
        self::deleted(function (self $shipmentPackageItem) {
            $shipmentPackageItem->shipmentPackage->recalcWeight();
        });
    }
}
