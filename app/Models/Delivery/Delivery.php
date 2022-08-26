<?php

namespace App\Models\Delivery;

use App\Models\Order\Order;
use App\Models\Order\OrderReturnReason;
use App\Models\WithHistory;
use Greensight\Logistics\Dto\Lists\DeliveryMethod;
use Greensight\Logistics\Dto\Lists\PointDto;
use Greensight\Logistics\Services\ListsService\ListsService;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Greensight\CommonMsa\Models\AbstractModel;
use Greensight\Logistics\Dto\Lists\DeliveryService;

/**
 * @OA\Schema(
 *     description="Доставка (одно или несколько отправлений, которые должны быть доставлены в один срок одной службой доставки до покупателя)",
 *     @OA\Property(
 *         property="order_id",
 *         type="integer",
 *         description="id заказа"
 *     ),
 *     @OA\Property(
 *         property="status",
 *         type="integer",
 *         description="статус"
 *     ),
 *     @OA\Property(
 *         property="delivery_method",
 *         type="integer",
 *         description="Метод доставки"
 *     ),
 *     @OA\Property(
 *         property="delivery_service",
 *         type="integer",
 *         description="Сервис доставки"
 *     ),
 *     @OA\Property(
 *         property="xml_id",
 *         type="string",
 *         description="идентификатор заказа на доставку в службе доставки"
 *     ),
 *     @OA\Property(
 *         property="tracknumber",
 *         type="string",
 *         description="трекинг код (соответствует providerNumber в apiship)"
 *     ),
 *     @OA\Property(
 *         property="barcode",
 *         type="string",
 *         description="штрихкод (соответствует additionalProviderNumber в apiship)"
 *     ),
 *     @OA\Property(
 *         property="error_xml_id",
 *         type="string",
 *         description="текст последней ошибки при создании/обновлении заказа на доставку в службе доставки"
 *     ),
 *     @OA\Property(
 *         property="status_xml_id",
 *         type="string",
 *         description="статус заказа на доставку в службе доставки"
 *     ),
 *     @OA\Property(
 *         property="payment_status",
 *         type="integer",
 *         description="статус оплаты"
 *     ),
 *     @OA\Property(
 *         property="payment_status_at",
 *         type="string",
 *         description="дата установки статуса оплаты"
 *     ),
 *     @OA\Property(
 *         property="is_problem",
 *         type="integer",
 *         description="флаг, что доставка проблемная"
 *     ),
 *     @OA\Property(
 *         property="is_problem_at",
 *         type="string",
 *         description="дата установки флага проблемной доставки"
 *     ),
 *     @OA\Property(
 *         property="is_canceled",
 *         type="integer",
 *         description="флаг, что доставка отменена"
 *     ),
 *     @OA\Property(
 *         property="is_canceled_at",
 *         type="string",
 *         description="дата установки флага отмены доставки"
 *     ),
 *     @OA\Property(
 *         property="tariff_id",
 *         type="integer",
 *         description="идентификатор тарифа на доставку из сервиса логистики"
 *     ),
 *     @OA\Property(
 *         property="point_id",
 *         type="integer",
 *         description="идентификатор пункта самовывоза из сервиса логистики"
 *     ),
 *     @OA\Property(
 *         property="number",
 *         type="string",
 *         description="номер доставки (номер_заказа-порядковый_номер_доставки)"
 *     ),
 *     @OA\Property(
 *         property="cost",
 *         type="number",
 *         description="стоимость доставки, полученная от службы доставки (не влияет на общую стоимость доставки по заказу!)"
 *     ),
 *     @OA\Property(
 *         property="delivery_sum",
 *         type="number",
 *         description="фактическая стоимость доставки, полученная от службы доставки"
 *     ),
 *     @OA\Property(
 *         property="total_sum",
 *         type="number",
 *         description="фактическая стоимость доставки с услугами, полученная от службы доставки"
 *     ),
 *     @OA\Property(
 *         property="width",
 *         type="number",
 *         description="ширина (расчитывается автоматически)"
 *     ),
 *     @OA\Property(
 *         property="height",
 *         type="number",
 *         description="высота (расчитывается автоматически)"
 *     ),
 *     @OA\Property(
 *         property="length",
 *         type="number",
 *         description="длина (расчитывается автоматически)"
 *     ),
 *     @OA\Property(
 *         property="weight",
 *         type="number",
 *         description="вес (расчитывается автоматически)"
 *     ),
 *     @OA\Property(
 *         property="receiver_name",
 *         type="string",
 *         description="имя получателя"
 *     ),
 *     @OA\Property(
 *         property="receiver_phone",
 *         type="string",
 *         description="телефон получателя"
 *     ),
 *     @OA\Property(
 *         property="receiver_email",
 *         type="string",
 *         description="e-mail получателя"
 *     ),
 *     @OA\Property(
 *         property="delivery_address",
 *         type="json",
 *         description="адрес доставки"
 *     ),
 *     @OA\Property(
 *         property="delivery_at",
 *         type="string",
 *         description="желаемая клиентом дата доставки"
 *     ),
 *     @OA\Property(
 *         property="delivered_at",
 *         type="string",
 *         description="Фактическая дата доставки"
 *     ),
 *     @OA\Property(
 *         property="delivery_time_start",
 *         type="string",
 *         description="желаемое клиентом время доставки от"
 *     ),
 *     @OA\Property(
 *         property="delivery_time_end",
 *         type="string",
 *         description="желаемое клиентом время доставки до"
 *     ),
 *     @OA\Property(
 *         property="delivery_time_code",
 *         type="string",
 *         description="код времени доставки"
 *     ),
 *     @OA\Property(
 *         property="dt",
 *         type="integer",
 *         description="delivery time - время доставки в днях, которое отдаёт ЛО"
 *     ),
 *     @OA\Property(
 *         property="pdd",
 *         type="string",
 *         description="planned delivery date - плановая дата, начиная с которой доставка может быть доставлена клиенту"
 *     ),
 *     @OA\Property(
 *         property="status_at",
 *         type="string",
 *         description=""
 *     ),
 *     @OA\Property(
 *         property="status_xml_id_at",
 *         type="string",
 *         description=""
 *     ),
 * )
 * Доставка (одно или несколько отправлений, которые должны быть доставлены в один срок одной службой доставки до покупателя)
 * Class Delivery
 *
 * @package App\Models\Delivery
 * @property int $order_id
 * @property int $status
 * @property int $delivery_method
 * @property int $delivery_service
 * @property string $xml_id - идентификатор заказа на доставку в службе доставки
 * @property string $tracknumber - трекинг код (соответствует providerNumber в apiship)
 * @property string $barcode - штрихкод (соответствует additionalProviderNumber в apiship)
 * @property string $error_xml_id - текст последней ошибки при создании/обновлении заказа на доставку в службе доставки
 * @property string $status_xml_id - статус заказа на доставку в службе доставки
 * @property int $payment_status - статус оплаты
 * @property \Illuminate\Support\Carbon|null $payment_status_at - дата установки статуса оплаты
 * @property int $is_problem - флаг, что доставка проблемная
 * @property Carbon|null $is_problem_at - дата установки флага проблемной доставки
 * @property int $is_canceled - флаг, что доставка отменена
 * @property int $return_reason_id - id причины отмены доставки
 * @property Carbon|null $is_canceled_at - дата установки флага отмены доставки
 * @property int $tariff_id - идентификатор тарифа на доставку из сервиса логистики
 * @property int $point_id - идентификатор пункта самовывоза из сервиса логистики
 * @property string $number - номер доставки (номер_заказа-порядковый_номер_доставки)
 * @property float $cost - стоимость доставки, полученная от службы доставки (не влияет на общую стоимость доставки по заказу!)
 * @property float $delivery_sum - фактическая стоимость доставки, полученная от службы доставки
 * @property float $total_sum - фактическая стоимость доставки с услугами, полученная от службы доставки
 * @property float $width - ширина (расчитывается автоматически)
 * @property float $height - высота (расчитывается автоматически)
 * @property float $length - длина (расчитывается автоматически)
 * @property float $weight - вес (расчитывается автоматически)
 * @property string $receiver_name - имя получателя
 * @property string $receiver_phone - телефон получателя
 * @property string $receiver_email - e-mail получателя
 * @property array $delivery_address - адрес доставки
 * @property Carbon $delivery_at - желаемая клиентом дата доставки
 * @property Carbon $delivered_at - Фактическая дата доставки
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
 * @property OrderReturnReason $orderReturnReason - причина возврата заказа
 */
class Delivery extends AbstractModel
{
    use WithHistory;
    use WithWeightAndSizes;

    private const SIDES = ['width', 'height', 'length'];

    /**
     * Заполняемые поля модели
     */
    public const FILLABLE = [
        'order_id',
        'status',
        'delivery_method',
        'delivery_service',
        'xml_id',
        'tracknumber',
        'barcode',
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
        'delivered_at',
    ];

    /** @var array */
    protected $fillable = self::FILLABLE;

    /** @var bool */
    protected static $unguarded = true;

    /** @var string */
    protected $table = 'delivery';

    /** @var array */
    protected $casts = [
        'delivery_address' => 'array',
        'delivery_at' => 'datetime',
        'delivered_at' => 'datetime',
        'weight' => 'float',
        'width' => 'float',
        'height' => 'float',
        'length' => 'float',
    ];

    /** @var array */
    protected static $restIncludes = ['shipments'];

    /**
     * @param string $orderNumber - номер заказа
     * @param int $i - порядковый номер доставки в заказе
     */
    public static function makeNumber(string $orderNumber, int $i): string
    {
        return $orderNumber . '-' . $i;
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    public function orderReturnReason(): BelongsTo
    {
        return $this->belongsTo(OrderReturnReason::class, 'return_reason_id');
    }

    protected function historyMainModel(): ?Order
    {
        return $this->order;
    }

    /** Номер для заказа в системе ЛО */
    public function getDeliveryServiceNumber(): string
    {
        $number = $this->number;
        if (!in_prod_stage()) {
            $number .= '-' . config('app.stage');
        }

        return $number;
    }

    protected function setDeliveryAddressAttribute($value)
    {
        $value = (array) $value;
        foreach ($value as &$item) {
            $item = (string) $item;
        }

        if ($value) {
            $value['address_string'] = $this->formDeliveryAddressString($value);
        }

        $this->attributes['delivery_address'] = json_encode($value);
    }

    public function getDeliveryAddressString(): string
    {
        if ($this->isPickup()) {
            /** @var ListsService $listService */
            $listService = resolve(ListsService::class);
            /** @var PointDto $point */
            $point = $listService->points($listService->newQuery()->setFilter('id', $this->point_id))->first();

            return $point->address['address_string'] ?? '';
        } else {
            if (!isset($this->delivery_address['address_string'])) {
                $deliveryAddress = $this->delivery_address;
                $deliveryAddress['address_string'] = $this->formDeliveryAddressString($deliveryAddress);
                $this->delivery_address = $deliveryAddress;
            }

            return (string) $this->delivery_address['address_string'];
        }
    }

    /**
     * @param array $address
     */
    public function formDeliveryAddressString(array $address): string
    {
        return (string) join(', ', array_filter([
            $address['post_index'] ?? null,
            $address['region'] ?? null,
            $address['city'] ?? null,
            $address['street'] ?? null,
            $address['house'] ?? null,
            $address['block'] ?? null,
            $address['flat'] ?? null,
        ]));
    }

    /**
     * Установить статус доставки у службы доставки (без сохранения!)
     */
    public function setStatusXmlId(string $status, ?Carbon $statusAt = null): self
    {
        if ($this->status_xml_id != $status || $this->status_xml_id_at != $statusAt) {
            $this->status_xml_id = $status;
            $this->status_xml_id_at = $statusAt ?: now();
        }

        return $this;
    }

    /**
     * Рассчитать вес доставки
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
            ->where('is_canceled', false)
            ->where(function($query) {
                return $query
                    ->where(fn($query) => $query->whereNotNull('xml_id')->where('xml_id', '!=', ''))
                    ->orWhere(fn($query) => $query->where('delivery_service', '=', DeliveryService::SERVICE_DPD)
                        ->whereNull('xml_id')
                        ->whereNotNull('error_xml_id')
                    );
            })
            ->where('delivery_service', '=', 5)
            ->whereNotIn('status', static::getFinalStatus());

        if ($withShipments) {
            $query->with('shipments');
        }

        return $query->get();
    }

    /**
     * Доставка с самовывозом?
     */
    public function isPickup(): bool
    {
        return $this->delivery_method == DeliveryMethod::METHOD_PICKUP;
    }

    /**
     * Доставка с курьерской доставкой?
     */
    public function isDelivery(): bool
    {
        return $this->delivery_method == DeliveryMethod::METHOD_DELIVERY;
    }

    /**
     * Доставка с постоплатой?
     */
    public function isPostPaid(): bool
    {
        return $this->order->is_postpaid;
    }
}
