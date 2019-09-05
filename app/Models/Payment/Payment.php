<?php

namespace App\Models\Payment;

use Carbon\Carbon;
use Greensight\CommonMsa\Models\AbstractModel;
use Illuminate\Support\Collection;

/**
 * Class Payment
 * @package App\Models
 *
 * @property int $order_id
 * @property float $sum
 * @property Carbon $created_at
 * @property Carbon $payed_at
 * @property int $status
 * @property array $parts
 *
 * @property Collection|PaymentPart[] $payments
 */
class Payment extends AbstractModel
{
    public $timestamps = false;
    
    protected $dates = ['created_at', 'payed_at'];
    protected $casts = ['parts' => 'array'];
    
    public function getPaymentsAttribute()
    {
        return collect($this->parts)->map(function (array $part) {
            return new PaymentPart($part);
        });
    }
    
    public function setPaymentsAttribute(Collection $payments)
    {
        $this->parts = $payments->map(function (PaymentPart $part) {
            return $part->toArray();
        });
    }
}
