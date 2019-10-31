<?php

namespace App\Models\Delivery;

use App\Models\OmsModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Коробка отправления
 * Class ShipmentPackage
 * @package App\Models\Delivery
 *
 * @property int $shipment_id
 * @property int $package_id
 * @property int $status
 *
 * @property float $width
 * @property float $height
 * @property float $length
 * @property float $weight
 * @property float $wrapper_weight
 *
 * @property-read Shipment $shipment
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
        'weight',
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
    
    public function recalcWeight(): void
    {
        $this->weight = $this->wrapper_weight + array_reduce((array)$this->items, function ($sum, $product) {
            return $sum + $product['weight'] * $product['qty'];
        });
    }
    
    public function setWrapper(float $weight, float $width, float $height, float $length): void
    {
        $this->wrapper_weight = $weight;
        $this->width = $width;
        $this->height = $height;
        $this->length = $length;
        $this->recalcWeight();
    }
    
    public function setProduct(int $offerId, array $data): void
    {
        $edited = false;
        $products = (array)$this->items;
        $toDelete = null;
        foreach ($products as $i => &$product) {
            if ($product['offer_id'] == $offerId) {
                $edited = true;
                if (isset($data['qty']) && $data['qty'] === 0) {
                    $toDelete = $i;
                    break;
                }
                foreach ($data as $field => $value) {
                    $product[$field] = $value;
                }
            }
        }
        if ($toDelete !== null) {
            unset($products[$toDelete]);
        }
        if (!$edited) {
            $data['offer_id'] = $offerId;
            $products[] = $data;
        }
        $this->items = $products;
        
        $this->recalcWeight();
    }
    
    protected static function boot()
    {
        parent::boot();
        
        self::saved(function (self $package) {
            $needRecalc = false;
            foreach (['weight', 'width', 'height', 'length'] as $field) {
                if ($package->getOriginal($field) != $package[$field]) {
                    $needRecalc = true;
                    break;
                }
            }
            if ($needRecalc && $package->shipment->cargo_id) {
                $package->shipment->cargo->recalc();
            }
            if ($needRecalc && $package->shipment->delivery_id) {
                $package->shipment->delivery->recalc();
            }
        });
        
        self::deleted(function (self $package) {
            if ($package->shipment->cargo_id) {
                $package->shipment->cargo->recalc();
            }
            if ($package->shipment->delivery_id) {
                $package->shipment->delivery->recalc();
            }
        });
    }
}
