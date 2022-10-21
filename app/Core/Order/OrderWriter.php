<?php

namespace App\Core\Order;

use App\Models\Order\Order;
use App\Models\Payment\Payment;
use Exception;
use Illuminate\Support\Collection;

class OrderWriter
{
    /**
     * Задать список оплат для заказа.
     *
     * @throws Exception
     */
    public function setPayments(Order $order, Collection $payments): void
    {
        $oldPayments = $order->payments;
        foreach ($oldPayments as $oldPayment) {
            /** @var Payment $newPayment */
            $newPayment = $payments->where('id', $oldPayment->id)->first();
            if ($newPayment) {
                $oldPayment->fill($newPayment->attributesToArray());
                $oldPayment->save();
            } else {
                $oldPayment->delete();
            }
        }
        foreach ($payments as $newPayment) {
            if (!$newPayment->id) {
                $newPayment->order_id = $order->id;
                $newPayment->save();
            }
        }
    }
}
