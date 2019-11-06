<?php

namespace App\Observers\Order;

use App\Models\History\History;
use App\Models\History\HistoryType;
use App\Models\Order\Order;

/**
 * Class OrderObserver
 * @package App\Observers\Order
 */
class OrderObserver
{
    /**
     * Handle the order "created" event.
     * @param  Order  $order
     * @return void
     */
    public function created(Order $order)
    {
        History::saveEvent(HistoryType::TYPE_CREATE, $order, $order);
    }

    /**
     * Handle the order "updated" event.
     * @param  Order  $order
     * @return void
     */
    public function updated(Order $order)
    {
        History::saveEvent(HistoryType::TYPE_UPDATE, $order, $order);
    }

    /**
     * Handle the order "saving" event.
     * @param  Order  $order
     * @return void
     */
    public function saving(Order $order)
    {
        if ($order->delivery_cost != $order->getOriginal('delivery_cost')) {
            $order->costRecalc(false);
        }
    }
    
    /**
     * Handle the order "deleting" event.
     * @param  Order  $order
     * @throws \Exception
     */
    public function deleting(Order $order)
    {
        History::saveEvent(HistoryType::TYPE_DELETE, $order, $order);
        
        if ($order->basket) {
            $order->basket->delete();
        }
        foreach ($order->deliveries as $delivery) {
            $delivery->delete();
        }
    }
}
