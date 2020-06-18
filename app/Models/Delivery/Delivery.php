<?php

namespace App\Models\Delivery;

use App\Models\OmsModel;
use App\Models\Order\Order;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Доставка (одно или несколько отправлений, которые должны быть доставлены в один срок одной службой доставки до покупателя)
 * Class Delivery
 *
 * @package App\Models\Delivery
 * @property int $order_id
 * @property int $status
 * @property int $delivery_method
 * @property int $delivery_service
 * @property string $xml_id - идентификатор заказа на доставку в службе доставки
 * @property string $error_xml_id - текст последней ошибки при создании/обновлении заказа на доставку в службе доставки
 * @property string $status_xml_id - статус заказа на доставку в службе доставки
 * @property int $payment_status - статус оплаты
 * @property \Illuminate\Support\Carbon|null $payment_status_at - дата установки статуса оплаты
 * @property int $is_problem - флаг, что доставка проблемная
 * @property Carbon|null $is_problem_at - дата установки флага проблемной доставки
 * @property int $is_canceled - флаг, что доставка отменена
 * @property Carbon|null $is_canceled_at - дата установки флага отмены доставки
 * @property int $tariff_id - идентификатор тарифа на доставку из сервиса логистики
 * @property int $point_id - идентификатор пункта самовывоза из сервиса логистики
 * @property string $number - номер доставки (номер_заказа-порядковый_номер_доставки)
 * @property float $cost - стоимость доставки, полученная от службы доставки (не влияет на общую стоимость доставки по заказу!)
 * @property float $width - ширина (расчитывается автоматически)
 * @property float $height - высота (расчитывается автоматически)
 * @property float $length - длина (расчитывается автоматически)
 * @property float $weight - вес (расчитывается автоматически)
 * @property string $receiver_name - имя получателя
 * @property string $receiver_phone - телефон получателя
 * @property string $receiver_email - e-mail получателя
 * @property array $delivery_address - адрес доставки
 * @property Carbon $delivery_at - желаемая клиентом дата доставки
 * @property string $delivery_time_start - желаемое клиентом время доставки от
 * @property string $delivery_time_end - желаемое клиентом время доставки до
 * @property string $delivery_time_code - код времени доставки
 * @property int $dt - delivery time - время доставки в днях, которое отдаёт ЛО
 * @property Carbon $pdd - planned delivery date - плановая дата,
 * начиная с которой доставка может быть доставлена клиенту
 * @property Carbon $status_at
 * @property Carbon $status_xml_id_at
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
        'delivery_time_start',
        'delivery_time_end',
        'delivery_time_code',
        'dt',
        'pdd',
        'receiver_name',
        'receiver_phone',
        'receiver_email',
        'delivery_address',
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
        'delivery_address' => 'array',
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

    /**
     * @param  string  $orderNumber - номер заказа
     * @param  int  $i - порядковый номер доставки в заказе
     * @return string
     */
    public static function makeNumber(string $orderNumber, int $i): string
    {
        return $orderNumber . '-' . $i;
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
     * @param $value
     */
    protected function setDeliveryAddressAttribute($value)
    {
        $value = (array)$value;
        foreach ($value as &$item) {
            $item = (string)$item;
        }
        $this->attributes['delivery_address'] = json_encode($value);
    }

    /**
     * Установить статус доставки у службы доставки (без сохранения!)
     * @param  string  $status
     * @param  Carbon|null $statusAt
     * @return self
     */
    public function setStatusXmlId(string $status, Carbon $statusAt = null): self
    {
        if ($this->status_xml_id != $status || $this->status_xml_id_at != $statusAt) {
            $this->status_xml_id = $status;
            $this->status_xml_id_at = $statusAt ?: now();
        }

        return $this;
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
     * Получить финальные статусы доставок
     * @return array
     */
    public static function getFinalStatus(): array
    {
        return [
            DeliveryStatus::DONE,
            DeliveryStatus::RETURNED,
        ];
    }

    /**
     * Получить доставки в работе: еще не доставлены
     * @param bool $withShipments - подгрузить отправления доставок
     * @return Collection|self[]
     */
    public static function deliveriesAtWork(bool $withShipments = false): Collection
    {
        $query = self::query()
            ->whereNotIn('status', static::getFinalStatus());
        if ($withShipments) {
            $query->with('shipments');
        }

        return $query->get();
    }

    /**
     * Получить доставки в доставке: выгружены в СД и еще не доставлены
     * @param bool $withShipments - подгрузить отправления доставок
     * @return Collection|self[]
     */
    public static function deliveriesInDelivery(bool $withShipments = false): Collection
    {
        $query = self::query()
            ->whereNotNull('xml_id')
            ->where('xml_id', '!=', '')
            ->whereNotIn('status', static::getFinalStatus());
        if ($withShipments) {
            $query->with('shipments');
        }

        return $query->get();
    }
}
