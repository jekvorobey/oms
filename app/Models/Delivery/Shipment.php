<?php

namespace App\Models\Delivery;

use App\Models\OmsModel;
use Greensight\CommonMsa\Rest\RestQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
 * //dynamic attributes
 * @property int $cost
 *
 * @property string $number - номер отправления (номер_заказа/порядковый_номер_отправления)
 *
 * @property-read Delivery $delivery
 * @property-read Collection|ShipmentItem[] $items
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
    protected static $restIncludes = ['delivery', 'packages', 'cargo'];
    
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
     * @return float
     */
    public function getCostAttribute(): float
    {
        $cost = 0.0;
    
        foreach ($this->items as $item) {
            $cost += $item->basketItem->qty * $item->basketItem->price;
        }
        
        return $cost;
    }
    
    /**
     * @param  Builder  $query
     * @param  RestQuery  $restQuery
     * @return Builder
     */
    public static function modifyQuery(Builder $query, RestQuery $restQuery): Builder
    {
        $modifiedRestQuery = clone $restQuery;
    
        $fields = $restQuery->getFields(static::restEntity());
        if (in_array('cost', $fields)) {
            $modifiedRestQuery->removeField(static::restEntity());
            if (($key = array_search('cost', $fields)) !== false) {
                unset($fields[$key]);
            }
            $restQuery->addFields(static::restEntity(), $fields);
            $query->with('items.basketItem');
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
        
        if (in_array('cost', $restQuery->getFields(static::restEntity()))) {
            $result['cost'] = $this->cost;
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
