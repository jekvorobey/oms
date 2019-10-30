<?php

namespace App\Models\Order;

use App\Models\OmsModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderComment extends OmsModel
{
    protected $table = 'orders_comments';

    /**
     * Заполняемые поля модели
     */
    const FILLABLE = [
        'text',
        'order_id',
    ];

    /**
     * @var array
     */
    protected $fillable = self::FILLABLE;

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    protected static function boot()
    {
        parent::boot();

        self::created(function (self $comment) {
            OrderHistoryEvent::saveEvent(OrderHistoryEvent::TYPE_COMMENT, $comment->order_id, $comment->order);
        });

        self::updated(function (self $comment) {
            OrderHistoryEvent::saveEvent(OrderHistoryEvent::TYPE_COMMENT, $comment->order_id, $comment->order);
        });
    }
}
