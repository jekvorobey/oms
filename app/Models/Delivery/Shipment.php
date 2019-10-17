<?php

namespace App\Models\Delivery;

use App\Models\OmsModel;
use App\Models\Order\OrderHistoryEvent;
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
 * @property string $number - номер отправления (номер_заказа/порядковый_номер_отправления)
 *
 * @property-read Delivery $delivery
 * @property-read Collection|ShipmentPackage[] $packages
 * @property-read Cargo $cargo
 */
class Shipment extends OmsModel
{
    /** @var string */
    protected $table = 'shipments';
    
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
    
    protected static function boot()
    {
        parent::boot();
        
        self::created(function (self $shipment) {
            OrderHistoryEvent::saveEvent(OrderHistoryEvent::TYPE_CREATE, $shipment->delivery->order_id, $shipment);
        });
    
        self::updated(function (self $shipment) {
            OrderHistoryEvent::saveEvent(OrderHistoryEvent::TYPE_UPDATE, $shipment->delivery->order_id, $shipment);
        });
        
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
            OrderHistoryEvent::saveEvent(OrderHistoryEvent::TYPE_DELETE, $shipment->delivery->order_id, $shipment);
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
