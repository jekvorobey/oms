<?php

namespace App\Models\Delivery;

use App\Core\Notifications\ShipmentNotification;
use App\Core\Order\OrderReader;
use App\Models\Basket\BasketItem;
use App\Models\OmsModel;
use App\Models\Order\Order;
use Greensight\CommonMsa\Rest\RestQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Pim\Dto\Offer\OfferDto;
use Pim\Dto\Product\ProductDto;
use Pim\Services\OfferService\OfferService;
use Pim\Services\ProductService\ProductService;

/**
 * Отправление (набор товаров с одного склада одного мерчанта)
 * Class Shipment
 * @package App\Models\Delivery
 *
 * @property int $delivery_id
 * @property int $merchant_id
 * @property int $delivery_service_zero_mile - сервис доставки нулевой мили
 * @property int $store_id
 * @property int $status
 * @property Carbon|null $status_at - дата установки статуса
 * @property int $payment_status - статус оплаты
 * @property Carbon|null $payment_status_at - дата установки статуса оплаты
 * @property int $is_problem - флаг, что отправление проблемное
 * @property Carbon|null $is_problem_at - дата установки флага проблемного отправления
 * @property int $is_canceled - флаг, что отправление отменено
 * @property Carbon|null $is_canceled_at - дата установки флага отмены отправления
 * @property int $cargo_id
 *
 * @property string $number - номер отправления (номер_доставки/порядковый_номер_отправления)
 * @property float $cost - сумма товаров отправления (расчитывается автоматически)
 * @property float $width - ширина (расчитывается автоматически)
 * @property float $height - высота (расчитывается автоматически)
 * @property float $length - длина (расчитывается автоматически)
 * @property float $weight - вес (расчитывается автоматически)
 * @property string $required_shipping_at - требуемая дата отгрузки
 * @property string $assembly_problem_comment - последнее сообщение мерчанта о проблеме со сборкой
 *
 * //dynamic attributes
 * @property int $package_qty - кол-во коробок отправления
 *
 * @property-read Delivery $delivery
 * @property-read Collection|ShipmentItem[] $items
 * @property-read Collection|BasketItem[] $basketItems
 * @property-read Collection|ShipmentPackage[] $packages
 * @property-read Cargo $cargo
 */
class Shipment extends OmsModel
{
    use WithWeightAndSizes;

    /**
     * Заполняемые поля модели
     */
    const FILLABLE = [
        'delivery_id',
        'merchant_id',
        'store_id',
        'cargo_id',
        'status',
        'number',
        'required_shipping_at',
        'assembly_problem_comment',
    ];

    /** @var string */
    public $notificator = ShipmentNotification::class;

    /**
     * @var array
     */
    protected $fillable = self::FILLABLE;

    /** @var array */
    private const SIDES = ['width', 'height', 'length'];

    /** @var string */
    protected $table = 'shipments';

    /**
     * @var array
     */
    protected static $restIncludes = ['delivery', 'packages', 'packages.items', 'cargo', 'items', 'basketItems'];

    /**
     * @param int $orderNumber - порядковый номер заказа
     * @param int $deliveryNumber - порядковый номер доставки
     * @param int $i - порядковый номер отправления в заказе
     * @return string
     */
    public static function makeNumber(int $orderNumber, int $deliveryNumber, int $i): string
    {
        return $orderNumber . '-' . $deliveryNumber . '-' . sprintf("%'02d", $i);
    }

    /**
     * @return BelongsTo
     */
    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    /**
     * @return HasMany
     */
    public function items(): HasMany
    {
        return $this->hasMany(ShipmentItem::class);
    }

    /**
     * @return BelongsToMany
     */
    public function basketItems(): BelongsToMany
    {
        return $this->belongsToMany(BasketItem::class, (new ShipmentItem())->getTable());
    }

    /**
     * @return HasMany
     */
    public function packages(): HasMany
    {
        return $this->hasMany(ShipmentPackage::class);
    }

    /**
     * @return BelongsTo
     */
    public function cargo(): BelongsTo
    {
        return $this->belongsTo(Cargo::class);
    }

    /**
     * Пересчитать сумму товаров отправления
     */
    public function costRecalc(bool $save = true): void
    {
        $cost = 0.0;
        $this->load('basketItems');

        foreach ($this->basketItems as $basketItem) {
            $cost += $basketItem->price;
        }

        $this->cost = $cost;

        if ($save) {
            $this->save();
        }
    }

    /**
     * Кол-во коробок отправления
     * @return int
     */
    public function getPackageQtyAttribute(): int
    {
        return (int)$this->packages()->count();
    }

    /**
     * Рассчитать вес отправления
     * @return float
     */
    public function calcWeight(): float
    {
        $weight = 0;
        $this->load(['packages', 'basketItems']);

        if ($this->packages && $this->packages->isNotEmpty()) {
            foreach ($this->packages as $package) {
                $weight += $package->wrapper_weight;
            }
        }
        foreach ($this->basketItems as $basketItem) {
            $weight += $basketItem->product['weight'] * $basketItem->qty;
        }

        return $weight;
    }

    /**
     * Рассчитать объем отправления
     * @return float
     */
    public function calcVolume(): float
    {
        $volume = 0;
        $this->load(['packages', 'basketItems']);

        if ($this->packages && $this->packages->isNotEmpty()) {
            foreach ($this->packages as $package) {
                $volume += $package->width * $package->height * $package->length;
            }
        } else {
            foreach ($this->basketItems as $basketItem) {
                $volume += $basketItem->product['width'] * $basketItem->product['height'] * $basketItem->product['length'] * $basketItem->qty;
            }
        }

        return $volume;
    }

    /**
     * Рассчитать значение максимальной стороны (длины, ширины или высоты) из всех отправлений
     * @return float
     */
    public function calcMaxSide(): float
    {
        $maxSide = 0;
        $this->load(['packages', 'basketItems']);

        if ($this->packages && $this->packages->isNotEmpty()) {
            foreach ($this->packages as $package) {
                foreach (self::SIDES as $side) {
                    if ($package[$side] > $maxSide) {
                        $maxSide = $package[$side];
                    }
                }
            }
        } else {
            foreach ($this->basketItems as $basketItem) {
                foreach (self::SIDES as $side) {
                    if ($basketItem->product[$side] > $maxSide) {
                        $maxSide = $basketItem->product[$side];
                    }
                }
            }
        }

        return $maxSide;
    }

    /**
     * Определить название максимальной стороны (длины, ширины или высоты) из всех отправлений
     * @param  float  $maxSide
     * @return string
     */
    public function identifyMaxSideName(float $maxSide): string
    {
        $maxSideName = 'width';
        $this->load(['packages', 'basketItems']);

        if ($this->packages && $this->packages->isNotEmpty()) {
            foreach ($this->packages as $package) {
                foreach (self::SIDES as $side) {
                    if ($package[$side] > $maxSide) {
                        $maxSide = $package[$side];
                        $maxSideName = $side;
                    }
                }
            }
        } else {
            foreach ($this->basketItems as $basketItem) {
                foreach (self::SIDES as $side) {
                    if ($basketItem->product[$side] > $maxSide) {
                        $maxSide = $basketItem->product[$side];
                        $maxSideName = $side;
                    }
                }
            }
        }

        return $maxSideName;
    }

    /**
     * @param  Builder  $query
     * @param  RestQuery  $restQuery
     * @return Builder
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
            $query->with('packages');
        }

        $merchantFilter = $restQuery->getFilter('merchant_id');
        $merchantId = $merchantFilter ? $merchantFilter[0][1] : 0;

        //Функция-фильтр id отправлений по id офферов
        $filterByOfferIds = function ($offerIds) {
            $shipmentIds = [];
            if ($offerIds) {
                $shipmentIds = Shipment::query()
                    ->select('id')
                    ->whereHas('items', function (Builder $query) use ($offerIds) {
                        $query->whereHas('basketItem', function (Builder $query) use ($offerIds) {
                            $query->where('offer_id', $offerIds);
                        });
                    })
                    ->get()
                    ->pluck('id')
                    ->toArray();
                if (!$shipmentIds) {
                    $shipmentIds = [-1];
                }
            }

            return $shipmentIds;
        };
        //Фильтр по коду оффера мерчанта из ERP мерчанта
        $offerXmlIdFilter = $restQuery->getFilter('offer_xml_id');
        if($offerXmlIdFilter) {
            [$op, $value] = $offerXmlIdFilter[0];
            /** @var OfferService $offerService */
            $offerService = resolve(OfferService::class);
            $offerQuery = $offerService->newQuery()
                ->addFields(OfferDto::entity(), 'id')
                ->setFilter('xml_id', $op, $value);
            if ($merchantId) {
                $offerQuery->setFilter('merchant_id', $merchantId);
            }
            $offerIds = $offerService->offers($offerQuery)->pluck('id')->toArray();

            $shipmentIds = $filterByOfferIds($offerIds);
            $existShipmentIds = $modifiedRestQuery->getFilter('id') ? $modifiedRestQuery->getFilter('id')[0][1] : [];
            if ($existShipmentIds) {
                $shipmentIds = array_values(array_intersect($existShipmentIds, $shipmentIds));
            }
            $modifiedRestQuery->setFilter('id', $shipmentIds);
            $modifiedRestQuery->removeFilter('offer_xml_id');
        }

        //Функция-фильтр по свойству товара
        $filterByProductField = function ($filterField, $productField) use (
            $restQuery,
            $modifiedRestQuery,
            $merchantId,
            $filterByOfferIds
        ) {
            $productVendorCodeFilter = $restQuery->getFilter($filterField);
            if($productVendorCodeFilter) {
                [$op, $value] = $productVendorCodeFilter[0];

                /** @var ProductService $productService */
                $productService = resolve(ProductService::class);
                $productQuery = $productService->newQuery()
                    ->addFields(ProductDto::entity(), 'id')
                    ->setFilter($productField, $op, $value);
                $productIds = $productService->products($productQuery)->pluck('id')->toArray();

                $offerIds = [];
                if ($productIds) {
                    /** @var OfferService $offerService */
                    $offerService = resolve(OfferService::class);
                    $offerQuery = $offerService->newQuery()
                        ->addFields(OfferDto::entity(), 'id')
                        ->setFilter('product_id', $productIds);
                    if ($merchantId) {
                        $offerQuery->setFilter('merchant_id', $merchantId);
                    }
                    $offerIds = $offerService->offers($offerQuery)->pluck('id')->toArray();
                }

                $shipmentIds = $filterByOfferIds($offerIds);
                $existShipmentIds = $modifiedRestQuery->getFilter('id') ? $modifiedRestQuery->getFilter('id')[0][1] : [];
                if ($existShipmentIds) {
                    $shipmentIds = array_values(array_intersect($existShipmentIds, $shipmentIds));
                }
                $modifiedRestQuery->setFilter('id', $shipmentIds);
                $modifiedRestQuery->removeFilter($filterField);
            }
        };
        //Фильтр по артикулу товара
        $filterByProductField('product_vendor_code', 'vendor_code');
        //Фильтр по бренду товара
        $filterByProductField('brands', 'brand_id');

        //Фильтр по службе доставки на последней миле
        $deliveryServiceFilter = $restQuery->getFilter('delivery_service');
        if($deliveryServiceFilter) {
            [$op, $value] = $deliveryServiceFilter[0];
            $query->whereHas('delivery', function (Builder $query) use ($value) {
                $query->where('delivery_service', $value);
            });
            $modifiedRestQuery->removeFilter('delivery_service');
        }

        //Фильтр по номеру заказа
        $orderNumberFilter = $restQuery->getFilter('order_number');
        if($orderNumberFilter) {
            [$op, $value] = $orderNumberFilter[0];

            $orderIds = Order::query()
                ->select('id')
                ->where('number', $value)
                ->get()
                ->pluck('id')
                ->all();

            $query->whereHas('delivery', function (Builder $query) use ($orderIds) {
                $query->whereIn('order_id', $orderIds);
            });
            $modifiedRestQuery->removeFilter('order_number');
        }

        return parent::modifyQuery($query, $modifiedRestQuery);
    }

    /**
     * @param  RestQuery  $restQuery
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
