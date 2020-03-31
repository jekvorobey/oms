<?php

namespace App\Observers\Order;

use App\Core\OrderSmsNotify;
use App\Models\History\History;
use App\Models\History\HistoryType;
use App\Models\Order\Order;
use App\Models\Order\OrderStatus;
use App\Models\Payment\PaymentStatus;
use Carbon\Carbon;

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

        $order->basket->is_belongs_to_order = true;
        $order->basket->save();
    }

    /**
     * Handle the order "updated" event.
     * @param  Order  $order
     * @return void
     */
    public function updated(Order $order)
    {
        History::saveEvent(HistoryType::TYPE_UPDATE, $order, $order);

        $this->notifyIfOrderPaid($order);
        $this->commitPaymentIfOrderDelivered($order);
    }

    /**
     * Handle the order "saving" event.
     * @param  Order  $order
     * @return void
     */
    public function saving(Order $order)
    {
        if ($order->status != $order->getOriginal('status')) {
            $order->status_at = Carbon::now();
        }

        if ($order->payment_status != $order->getOriginal('payment_status')) {
            $order->payment_status_at = Carbon::now();
        }

        if ($order->is_problem != $order->getOriginal('is_problem')) {
            $order->is_problem_at = Carbon::now();
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

    /**
     * @param Order $order
     */
    private function notifyIfOrderPaid(Order $order): void
    {
        $oldPaymentStatus = $order->getOriginal('payment_status');
        $newPaymentStatus = $order->payment_status;
        if ($oldPaymentStatus != $newPaymentStatus) {
            if ($newPaymentStatus == PaymentStatus::PAID) {
                OrderSmsNotify::payed($order);
            }
        }
    }

    private function commitPaymentIfOrderDelivered(Order $order): void
    {
        $oldStatus = $order->getOriginal('status');
        $newStatus = $order->status;
        if ($newStatus == OrderStatus::DONE && $newStatus != $oldStatus) {
            foreach ($order->payments as $payment) {
                if ($payment->status == PaymentStatus::HOLD) {
                    $payment->commitHolded();
                }
            }
        }
    }
}
