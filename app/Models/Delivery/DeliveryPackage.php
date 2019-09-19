<?php

namespace App\Models\Delivery;

use App\Models\OmsModel;
use App\Models\Order\Order;
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
}
