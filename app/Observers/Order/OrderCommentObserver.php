<?php

namespace App\Observers\Order;

use App\Models\History\History;
use App\Models\History\HistoryType;
use App\Models\Order\OrderComment;

/**
 * Class OrderCommentObserver
 * @package App\Observers\Order
 */
class OrderCommentObserver
{
    /**
     * Handle the order comment "created" event.
     * @return void
     */
    public function created(OrderComment $orderComment)
    {
        History::saveEvent(HistoryType::TYPE_COMMENT, $orderComment->order, $orderComment);
    }

    /**
     * Handle the order comment "updated" event.
     * @return void
     */
    public function updated(OrderComment $orderComment)
    {
        History::saveEvent(HistoryType::TYPE_COMMENT, $orderComment->order, $orderComment);
    }
}
