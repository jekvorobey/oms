<?php

namespace App\Core\Order;

use App\Models\Order\Order;
use App\Models\Payment\Payment;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class OrderWriter
{
    public function create(array $data): ?int
    {
        $now = CarbonImmutable::now();
        $order = new Order();
        $order->fill($data);
        
        $order->number = 'IBT' . $now->format('Ymdhis');
        return $order->save() ? $order->id : null;
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
