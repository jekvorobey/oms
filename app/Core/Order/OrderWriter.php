<?php

namespace App\Core\Order;

use App\Models\Order;
use App\Models\Payment\Payment;
use Illuminate\Support\Collection;

class OrderWriter
{
    public function create(array $data)
    {
        // todo реализовать создавние заказа
    }
    
    public function update(int $id, array $data)
    {
        // todo реализовать редактирование заказа
    }
    
    public function delete(int $id)
    {
        // todo реализовать удаение заказа
    }
    
    /**
     * Задать список оплат для заказа.
     *
     * @param Order $order
     * @param Collection $payments
     * @throws \Exception
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
