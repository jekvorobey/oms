<?php

namespace App\Models\Delivery;

use App\Models\OmsModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Коробка отправления
 * Class ShipmentPackage
 * @package App\Models\Delivery
 *
 * @property int $shipment_id
 * @property int $package_id
 *
 * @property float $width
 * @property float $height
 * @property float $length
 * @property float $weight - вес (расчитывается автоматически)
 * @property float $wrapper_weight
 *
 * @property-read Shipment $shipment
 * @property-read Collection|ShipmentPackageItem[] $items
 */
class ShipmentPackage extends OmsModel
{
    /**
     * Заполняемые поля модели
     */
    const FILLABLE = [
        'shipment_id',
        'package_id',
        'status',
        'width',
        'height',
        'length',
        'wrapper_weight',
    ];
    
    /**
     * @var array
     */
    protected $fillable = self::FILLABLE;
    
    /** @var string */
    protected $table = 'shipment_packages';
    
    /** @var array */
    protected $casts = [
        'wrapper_weight' => 'float',
        'weight' => 'float',
        'width' => 'float',
        'height' => 'float',
        'length' => 'float',
    ];
    
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
    
    /**
     * @return HasMany
     */
    public function items(): HasMany
    {
        return $this->hasMany(ShipmentPackageItem::class);
    }
    
    /**
     * @param  bool  $save
     */
    public function recalcWeight(bool $save = true): void
    {
        $this->weight = $this->wrapper_weight + $this->items->reduce(function ($sum, ShipmentPackageItem $item) {
            return $sum + $item->basketItem->weight * $item->qty;
        });
        
        if ($save) {
            $this->save();
        }
    }
}
