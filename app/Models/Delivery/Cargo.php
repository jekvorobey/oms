<?php

namespace App\Models\Delivery;

use App\Models\OmsModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Груз
 * Class Cargo
 * @package App\Models\Delivery
 *
 * @property int $status
 * @property int $delivery_method
 *
 * @property string $xml_id
 * @property double $width
 * @property double $height
 * @property double $length
 * @property double $weight
 *
 * @property-read Collection|Shipment[] $shipments
 */
class Cargo extends OmsModel
{
    use WithWeightAndSizes;
    
    /** @var array */
    private const SIDES = ['width', 'height', 'length'];
    
    /** @var string */
    protected $table = 'cargo';
    
    /** @var array */
    protected $casts = [
        'weight' => 'float',
        'width' => 'float',
        'height' => 'float',
        'length' => 'float',
    ];
    
    /**
     * @return HasMany
     */
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }
    
    /**
     * Рассчитать вес груза
     * @return float
     */
    public function calcWeight(): float
    {
        $weight = 0;
        
        foreach ($this->shipments as $shipment) {
            foreach ($shipment->packages as $package) {
                $weight += $package->weight;
            }
        }
        
        return $weight;
    }
    
    /**
     * Рассчитать объем груза
     * @return float
     */
    public function calcVolume(): float
    {
        $volume = 0;
        
        foreach ($this->shipments as $shipment) {
            foreach ($shipment->packages as $package) {
                $volume += $package->width * $package->height * $package->length;
            }
        }
        
        return $volume;
    }
    
    /**
     * Рассчитать значение максимальной стороны (длины, ширины или высоты) из всех коробок груза
     * @return float
     */
    public function calcMaxSide(): float
    {
        $maxSide = 0;
    
        foreach ($this->shipments as $shipment) {
            foreach ($shipment->packages as $package) {
                foreach (self::SIDES as $side) {
                    if ($package[$side] > $maxSide) {
                        $maxSide = $package[$side];
                    }
                }
            }
        }
        
        return $maxSide;
    }
    
    /**
     * Определить название максимальной стороны (длины, ширины или высоты) из всех коробок груза
     * @param  float  $maxSide
     * @return string
     */
    public function identifyMaxSideName(float $maxSide): string
    {
        $maxSideName = 'width';
    
        foreach ($this->shipments as $shipment) {
            foreach ($shipment->packages as $package) {
                foreach (self::SIDES as $side) {
                    if ($package[$side] > $maxSide) {
                        $maxSide = $package[$side];
                        $maxSideName = $side;
                    }
                }
            }
        }
        
        return $maxSideName;
    }
}
