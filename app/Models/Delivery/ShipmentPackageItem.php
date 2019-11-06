<?php

namespace App\Models\Delivery;

use App\Models\Basket\BasketItem;
use App\Models\OmsModel;
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
}
