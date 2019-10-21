<?php

namespace App\Models\Delivery;

use App\Models\OmsModel;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Доставка (одно или несколько отправлений, которые должны быть доставлены в один срок одной службой доставки до покупателя)
 * Class Delivery
 * @package App\Models\Delivery
 *
 * @property int $order_id
 * @property int $status
 * @property int $delivery_method
 * @property int $delivery_service
 *
 * @property string $xml_id - идентификатор заказа на доставку в службе доставки
 * @property string $number - номер доставки (номер_заказа-порядковый_номер_отправления)
 * @property double $width
 * @property double $height
 * @property double $length
 * @property double $weight
 * @property Carbon $delivery_at
 *
 * @property-read Collection|Shipment[] $shipments
 */
class Delivery extends OmsModel
{
    use WithWeightAndSizes;
    
    /**
     * Заполняемые поля модели
     */
    const FILLABLE = [
        'order_id',
        'status',
        'delivery_method',
        'delivery_service',
        'xml_id',
        'number',
        'width',
        'height',
        'length',
        'weight',
        'delivery_at',
    ];
    
    /**
     * @var array
     */
    protected $fillable = self::FILLABLE;
    
    /** @var array */
    private const SIDES = ['width', 'height', 'length'];
    
    /** @var string */
    protected $table = 'delivery';
    
    /** @var array */
    protected $casts = [
        'delivery_at' => 'datetime',
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
     * Рассчитать вес доставки
     * @return float
     */
    public function calcWeight(): float
    {
        $weight = 0;
        
        foreach ($this->shipments as $shipment) {
            $weight += $shipment->weight;
        }
        
        return $weight;
    }
    
    /**
     * Рассчитать объем доставки
     * @return float
     */
    public function calcVolume(): float
    {
        $volume = 0;
        
        foreach ($this->shipments as $shipment) {
            $volume += $shipment->width * $shipment->height * $shipment->length;
        }
        
        return $volume;
    }
    
    /**
     * Рассчитать значение максимальной стороны (длины, ширины или высоты) из всех отправлений доставки
     * @return float
     */
    public function calcMaxSide(): float
    {
        $maxSide = 0;
        
        foreach ($this->shipments as $shipment) {
            foreach (self::SIDES as $side) {
                if ($shipment[$side] > $maxSide) {
                    $maxSide = $shipment[$side];
                }
            }
        }
        
        return $maxSide;
    }
    
    /**
     * Определить название максимальной стороны (длины, ширины или высоты) из всех отправлений доставки
     * @param  float  $maxSide
     * @return string
     */
    public function identifyMaxSideName(float $maxSide): string
    {
        $maxSideName = 'width';
        
        foreach ($this->shipments as $shipment) {
            foreach (self::SIDES as $side) {
                if ($shipment[$side] > $maxSide) {
                    $maxSide = $shipment[$side];
                    $maxSideName = $side;
                }
            }
        }
        
        return $maxSideName;
    }
}
