<?php

namespace App\Models\Payment;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
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
 * @property array $data
 *
 * @property-read Order $order
 */
class Payment extends Model
{
    public $timestamps = false;
    protected static $unguarded = true;
    
    protected $dates = ['created_at', 'payed_at'];
    protected $casts = ['data' => 'array'];
    
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        if (!$this->created_at) {
            $this->created_at = Carbon::now();
        }
        if (!$this->data) {
            $this->data = [];
        }
    }
    
    public function paymentSystem()
    {
        switch ($this->data['paymentSystem'] ?? '') {
            case 'testing': return new LocalPaymentSystem($this);
        }
    }
    
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
    
    protected static function boot()
    {
        parent::boot();
        self::saved(function (self $payment) {
            if ($payment->getOriginal('status') != $payment->status) {
                $payment->order->refreshStatus();
            }
        });
    }
    
    
}
