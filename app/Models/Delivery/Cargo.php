<?php

namespace App\Models\Delivery;

use App\Models\OmsModel;
use Greensight\CommonMsa\Rest\RestQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Груз - совокупность отправлений для доставки на нулевой миле (доставка от мерчанта до распределительного центра)
 * Class Cargo
 * @package App\Models\Delivery
 *
 * @property int $merchant_id
 * @property int $store_id
 * @property int $status
 * @property Carbon|null $status_at - дата установки статуса
 * @property int $is_problem - флаг, что у груза проблемы при отгрузке
 * @property Carbon|null $is_problem_at - дата установки флага проблемного груза
 * @property int $is_canceled - флаг, что груз отменен
 * @property Carbon|null $is_canceled_at - дата установки флага отмены груза
 * @property int $delivery_service
 *
 * @property string $cdek_intake_number - Номер заявки СДЭК на вызов курьера
 * @property string $xml_id
 * @property string $error_xml_id - текст последней ошибки при создании заявки на вызов курьера для забора груза в службе доставки
 * @property float $width - ширина (расчитывается автоматически)
 * @property float $height - высота (расчитывается автоматически)
 * @property float $length - длина (расчитывается автоматически)
 * @property float $weight - вес (расчитывается автоматически)
 * @property string $shipping_problem_comment - последнее сообщение мерчанта о проблеме с отгрузкой
 *
 * //dynamic attributes
 * @property int $package_qty - кол-во коробок груза
 *
 * @property-read Collection|Shipment[] $shipments
 */
class Cargo extends OmsModel
{
    use WithWeightAndSizes;

    private const SIDES = ['width', 'height', 'length'];

    /**
     * Заполняемые поля модели
     */
    public const FILLABLE = [
        'merchant_id',
        'store_id',
        'status',
        'delivery_service',
        'cdek_intake_number',
        'xml_id',
        'shipping_problem_comment',
    ];

    /** @var array */
    protected $fillable = self::FILLABLE;

    /** @var string */
    protected $table = 'cargo';

    /** @var array */
    protected $casts = [
        'weight' => 'float',
        'width' => 'float',
        'height' => 'float',
        'length' => 'float',
    ];

    /** @var array */
    protected static $restIncludes = ['shipments', 'shipments.basketItems'];

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    /**
     * Кол-во коробок груза
     */
    public function getPackageQtyAttribute(): int
    {
        return (int) $this->shipments->reduce(function ($sum, Shipment $shipment) {
            return $sum + $shipment->packages()->count();
        });
    }

    /**
     * Рассчитать вес груза
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

    /**
     * @throws \Pim\Core\PimException
     */
    public static function modifyQuery(Builder $query, RestQuery $restQuery): Builder
    {
        $modifiedRestQuery = clone $restQuery;

        $fields = $restQuery->getFields(static::restEntity());
        if (in_array('package_qty', $fields)) {
            $modifiedRestQuery->removeField(static::restEntity());
            if (($key = array_search('package_qty', $fields)) !== false) {
                unset($fields[$key]);
            }
            $restQuery->addFields(static::restEntity(), $fields);
            $query->with('shipments.packages');
        }

        //Фильтр по номеру отправления в грузе
        $shipmentNumberFilter = $restQuery->getFilter('shipment_number');
        if ($shipmentNumberFilter) {
            [$op, $value] = $shipmentNumberFilter[0];
            $cargoIds = array_filter(Shipment::query()
                ->select('cargo_id')
                ->where('number', $op, $value)
                ->get()
                ->pluck('cargo_id')
                ->all());
            $modifiedRestQuery->setFilter('id', $cargoIds);
            $modifiedRestQuery->removeFilter('shipment_number');
        }

        return parent::modifyQuery($query, $modifiedRestQuery);
    }

    /**
     * @return array
     */
    public function toRest(RestQuery $restQuery): array
    {
        $result = $this->toArray();

        if (in_array('package_qty', $restQuery->getFields(static::restEntity()))) {
            $result['package_qty'] = $this->package_qty;
        }

        return $result;
    }
}
