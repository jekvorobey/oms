<?php

namespace App\Models\Payment;

use App\Models\Order;
use Carbon\Carbon;
use Greensight\CommonMsa\Models\AbstractModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class Payment
 * @package App\Models
 *
 * @property int $order_id
 * @property float $sum
 * @property Carbon $created_at
 * @property Carbon $payed_at
 * @property int $status
 * @property int $type
 * @property array $parts
 *
 */
class Payment extends AbstractModel
{
    public $timestamps = false;
    
    protected $dates = ['created_at', 'payed_at'];
    protected $casts = ['data' => 'array'];
    
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
