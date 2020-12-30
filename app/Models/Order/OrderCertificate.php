<?php

namespace App\Models\Order;

use App\Models\OmsModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class OrderCertificate
 * @package App\Models\Order
 *
 * @property int $id
 * @property int $order_id
 * @property int $card_id
 * @property string $code
 * @property string $status
 * @property int $amount
 */

class OrderCertificate extends OmsModel
{
    protected $table = 'order_certificates';

    const STATUS_NONE = 0;
    const STATUS_APPLIED = 1;

    protected $fillable =  [
        'order_id',
        'card_id',
        'code',
        'status',
        'amount',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
