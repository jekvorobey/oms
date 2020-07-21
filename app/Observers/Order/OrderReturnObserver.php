<?php

namespace App\Observers\Order;

use App\Models\History\History;
use App\Models\History\HistoryType;
use App\Models\Order\OrderReturn;

/**
 * Class OrderReturnObserver
 * @package App\Observers\Order
 */
class OrderReturnObserver
{
    /**
     * Handle the order return "created" event.
     * @param  OrderReturn  $orderReturn
     * @return void
     */
    public function created(OrderReturn $orderReturn)
    {
        History::saveEvent(HistoryType::TYPE_CREATE, $orderReturn->order, $orderReturn);
    }

    /**
     * Handle the order return "updated" event.
     * @param  OrderReturn  $orderReturn
     * @return void
     * @throws \Exception
     */
    public function updated(OrderReturn $orderReturn)
    {
        History::saveEvent(HistoryType::TYPE_UPDATE, $orderReturn->order, $orderReturn);
    }

    /**
     * Handle the order return "saving" event.
     * @param  OrderReturn  $orderReturn
     * @return void
     */
    public function saving(OrderReturn $orderReturn)
    {
        //Данная команда должна быть в самом низу перед всеми $this->set*Status()
        $this->setStatusAt($orderReturn);
    }

    /**
     * Handle the order return "deleting" event.
     * @param  OrderReturn  $orderReturn
     * @throws \Exception
     */
    public function deleting(OrderReturn $orderReturn)
    {
        History::saveEvent(HistoryType::TYPE_DELETE, $orderReturn->order, $orderReturn);

        foreach ($orderReturn->items as $item) {
            $item->delete();
        }
    }

    /**
     * Установить дату изменения статуса возврата
     * @param  OrderReturn $order
     */
    protected function setStatusAt(OrderReturn $orderReturn): void
    {
        if ($orderReturn->status != $orderReturn->getOriginal('status')) {
            $orderReturn->status_at = now();
        }
    }
}
