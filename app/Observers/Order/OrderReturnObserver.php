<?php

namespace App\Observers\Order;

use App\Models\Order\OrderReturn;
use App\Services\PaymentService\PaymentService;

/**
 * Class OrderReturnObserver
 * @package App\Observers\Order
 */
class OrderReturnObserver
{
    /**
     * Handle the order return "created" event.
     * @return void
     */
    public function created(OrderReturn $orderReturn)
    {
        (new PaymentService())->refund($orderReturn->order, $orderReturn->price);
    }

    /**
     * Handle the order return "updated" event.
     * @return void
     * @throws \Exception
     */
    public function updated(OrderReturn $orderReturn)
    {
        if ($orderReturn->wasChanged('price')) {
            (new PaymentService())->refund($orderReturn->order, $orderReturn->price - $orderReturn->getOriginal('price'));
        }
    }

    /**
     * Handle the order return "saving" event.
     * @return void
     */
    public function saving(OrderReturn $orderReturn)
    {
        //Данная команда должна быть в самом низу перед всеми $this->set*Status()
        $this->setStatusAt($orderReturn);
    }

    /**
     * Handle the order return "deleting" event.
     * @throws \Exception
     */
    public function deleting(OrderReturn $orderReturn)
    {
        foreach ($orderReturn->items as $item) {
            $item->delete();
        }
    }

    /**
     * Установить дату изменения статуса возврата
     * @param OrderReturn $order
     */
    protected function setStatusAt(OrderReturn $orderReturn): void
    {
        if ($orderReturn->status != $orderReturn->getOriginal('status')) {
            $orderReturn->status_at = now();
        }
    }
}
