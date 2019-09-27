<?php

namespace App\Models\Delivery;

use App\Models\OmsModel;
use App\Models\Order\Order;
use App\Models\Order\OrderHistoryEvent;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class Shipment
 * @package App\Models\Delivery
 *
 * @property int $order_id
 * @property array $items
 * @property Carbon $delivery_at
 * @property int $status
 * @property int $cargo_id
 *
 * @property-read Order $order
 * @property-read Collection|ShipmentPackage[] $packages
 * @property-read Cargo $cargo
 */
class Shipment extends OmsModel
{
    protected $casts = [
        'items' => 'array',
        'delivery_at' => 'datetime'
    ];
    
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
    
    public function packages(): HasMany
    {
        return $this->hasMany(ShipmentPackage::class);
    }
    
    public function cargo(): BelongsTo
    {
        return $this->belongsTo(Cargo::class);
    }
    
    protected static function boot()
    {
        parent::boot();
        self::created(function (self $shipment) {
            OrderHistoryEvent::saveEvent(OrderHistoryEvent::TYPE_CREATE, $shipment->order_id, $shipment);
        });
    
        self::updated(function (self $shipment) {
            OrderHistoryEvent::saveEvent(OrderHistoryEvent::TYPE_UPDATE, $shipment->order_id, $shipment);
        });
        
        self::saved(function (self $shipment) {
            $oldCargoId = $shipment->getOriginal('cargo_id');
            if ($oldCargoId != $shipment->cargo_id) {
                if ($oldCargoId) {
                    $oldCargo = Cargo::find($oldCargoId);
                    if ($oldCargo) {
                        $oldCargo->recalc();
                    }
                }
                if ($shipment->cargo_id) {
                    $newCargo = Cargo::find($shipment->cargo_id);
                    if ($newCargo) {
                        $newCargo->recalc();
                    }
                }
            }
        });
    
        self::deleting(function (self $shipment) {
            OrderHistoryEvent::saveEvent(OrderHistoryEvent::TYPE_DELETE, $shipment->order_id, $shipment);
            foreach ($shipment->packages as $package) {
                $package->delete();
            }
        });
        
        self::deleted(function (self $shipment) {
            if ($shipment->cargo_id) {
                $newCargo = Cargo::find($shipment->cargo_id);
                if ($newCargo) {
                    $newCargo->recalc();
                }
            }
        });
    }
}
