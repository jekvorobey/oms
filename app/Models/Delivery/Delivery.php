<?php

namespace App\Models\Delivery;

use App\Models\OmsModel;
use App\Models\Order\Order;
use Carbon\Carbon;
use Greensight\Logistics\Dto\Lists\DeliveryOrderStatus\DeliveryOrderStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

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
 * @property string $status_xml_id - статус заказа на доставку в службе доставки
 * @property int $tariff_id - идентификатор тарифа на доставку из сервиса логистики
 * @property int $point_id - идентификатор пункта самовывоза из сервиса логистики
 * @property string $number - номер доставки (номер_заказа-порядковый_номер_отправления)
 * @property float $cost - стоимость доставки, полученная от службы доставки (не влияет на общую стоимость доставки по заказу!)
 * @property float $width - ширина (расчитывается автоматически)
 * @property float $height - высота (расчитывается автоматически)
 * @property float $length - длина (расчитывается автоматически)
 * @property float $weight - вес (расчитывается автоматически)
 * @property Carbon $delivery_at
 * @property Carbon $status_at
 * @property Carbon $status_xml_id_at
 *
 * @property-read Order $order
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
        'tariff_id',
        'point_id',
        'number',
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
     * @var array
     */
    protected static $restIncludes = ['shipments'];
    
    public static function makeNumber(string $number, int $i): string
    {
        return $number . '_' . $i;
    }
    
    /**
     * @return BelongsTo
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
    
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
    
    /**
     * Получить доставки в работе: выгружены в СД и еще не доставлены
     * @return Collection|self[]
     */
    public static function deliveriesAtWork(): Collection
    {
        return self::query()
            ->whereNotNull('xml_id')
            ->where('xml_id', '!=', '')
            ->whereNotIn('status', [
                DeliveryOrderStatus::STATUS_DONE,
                DeliveryOrderStatus::STATUS_RETURNED,
                DeliveryOrderStatus::STATUS_LOST,
                DeliveryOrderStatus::STATUS_CANCEL,
            ])
            ->get()
            ->keyBy('number');
    }
}
