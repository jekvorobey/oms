<?php

namespace App\Models\Order;

use App\Models\History\History;
use App\Models\History\HistoryType;
use App\Models\OmsModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class OrderComment
 * @package App\Models\Order
 *
 * @property int $order_id
 * @property string $text
 *
 * @property Order $order
 */
class OrderComment extends OmsModel
{
    /** @var string */
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
    
    /**
     * @return BelongsTo
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    protected static function boot()
    {
        parent::boot();

        self::created(function (self $orderComment) {
            History::saveEvent(HistoryType::TYPE_COMMENT, $orderComment->order, $orderComment);
        });

        self::updated(function (self $orderComment) {
            History::saveEvent(HistoryType::TYPE_COMMENT, $orderComment->order, $orderComment);
        });
    }
}
