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
 *
 * @property-read Order $order
 * @property-read Collection|ShipmentPackage[] $packages
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
    
    protected static function boot()
    {
        parent::boot();
        self::created(function (self $package) {
            OrderHistoryEvent::saveEvent(OrderHistoryEvent::TYPE_CREATE, $package->order_id, $package);
        });
    
        self::updated(function (self $package) {
            OrderHistoryEvent::saveEvent(OrderHistoryEvent::TYPE_UPDATE, $package->order_id, $package);
        });
    
        self::deleting(function (self $package) {
            OrderHistoryEvent::saveEvent(OrderHistoryEvent::TYPE_DELETE, $package->order_id, $package);
        });
    }
}
