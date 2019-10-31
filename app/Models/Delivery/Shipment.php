<?php

namespace App\Models\Delivery;

use App\Models\Basket\BasketItem;
use App\Models\OmsModel;
use Greensight\CommonMsa\Rest\RestQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
 * @property int $store_id
 * @property int $status
 * @property int $cargo_id
 *
 * @property string $number - номер отправления (номер_заказа/порядковый_номер_отправления)
 * @property float $cost - сумма товаров отправления
 * @property string $required_shipping_at - требуемая дата отгрузки
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
    /**
     * Заполняемые поля модели
     */
    const FILLABLE = [
        'delivery_id',
        'merchant_id',
        'store_id',
        'cargo_id',
        'number',
        'cost',
        'required_shipping_at',
    ];
    
    /**
     * @var array
     */
    protected $fillable = self::FILLABLE;
    
    /** @var string */
    protected $table = 'shipments';
    
    /**
     * @var array
     */
    protected static $restIncludes = ['delivery', 'packages', 'cargo', 'items', 'basketItems'];
    
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
    public function costRecalc(): void
    {
        $cost = 0.0;
        $this->load('items.basketItem');
    
        foreach ($this->items as $item) {
            $cost += $item->basketItem->cost;
        }
        
        $this->cost = $cost;
        $this->save();
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
    
    protected static function boot()
    {
        parent::boot();
        
        //todo Доделать сохранение истории
        /*self::created(function (self $shipment) {
            OrderHistoryEvent::saveEvent(OrderHistoryEvent::TYPE_CREATE, $shipment->delivery->order_id, $shipment);
        });
    
        self::updated(function (self $shipment) {
            OrderHistoryEvent::saveEvent(OrderHistoryEvent::TYPE_UPDATE, $shipment->delivery->order_id, $shipment);
        });*/
        
        self::saved(function (self $shipment) {
            $oldCargoId = $shipment->getOriginal('cargo_id');
            if ($oldCargoId != $shipment->cargo_id) {
                if ($oldCargoId) {
                    /** @var Cargo $oldCargo */
                    $oldCargo = Cargo::find($oldCargoId);
                    if ($oldCargo) {
                        $oldCargo->recalc();
                    }
                }
                if ($shipment->cargo_id) {
                    /** @var Cargo $newCargo */
                    $newCargo = Cargo::find($shipment->cargo_id);
                    if ($newCargo) {
                        $newCargo->recalc();
                    }
                }
            }
        });
    
        self::deleting(function (self $shipment) {
            //todo Доделать сохранение истории
            //OrderHistoryEvent::saveEvent(OrderHistoryEvent::TYPE_DELETE, $shipment->delivery->order_id, $shipment);
            foreach ($shipment->packages as $package) {
                $package->delete();
            }
        });
        
        self::deleted(function (self $shipment) {
            if ($shipment->cargo_id) {
                /** @var Cargo $newCargo */
                $newCargo = Cargo::find($shipment->cargo_id);
                if ($newCargo) {
                    $newCargo->recalc();
                }
            }
        });
    }
}
