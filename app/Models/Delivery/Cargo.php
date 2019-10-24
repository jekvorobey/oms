<?php

namespace App\Models\Delivery;

use App\Models\OmsModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Груз - совокупность отправлений для доставки на нулевой миле (доставка от мерчанта до распределительного центра)
 * Class Cargo
 * @package App\Models\Delivery
 *
 * @property int $merchant_id
 * @property int $store_id
 * @property int $status
 * @property int $delivery_service
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
    
    /**
     * Заполняемые поля модели
     */
    const FILLABLE = [
        'merchant_id',
        'store_id',
        'status',
        'delivery_service',
        'xml_id',
        'width',
        'height',
        'length',
        'weight',
    ];
    
    /**
     * @var array
     */
    protected $fillable = self::FILLABLE;
    
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
     * @var array
     */
    protected static $restIncludes = ['shipments'];
    
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
