<?php

namespace App\Models\Delivery;

use App\Models\OmsModel;
use App\Models\Order\Order;
use App\Models\Order\OrderHistoryEvent;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class DeliveryPackage
 * @package App\Models\Delivery
 *
 * @property int $order_id
 * @property array $items
 * @property Carbon $delivery_at
 *
 * @property-read Order $order
 */
class DeliveryPackage extends OmsModel
{
    protected static $unguarded = true;
    
    protected $casts = [
        'items' => 'array',
        'delivery_at' => 'datetime'
    ];
    
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
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
