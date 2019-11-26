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
        $order->customer_id = $data['customer_id'];
        $order->basket_id = $data['basket_id'];
        $order->cost = $data['cost'];
        
        $order->delivery_service = $data['delivery_service'] ?? null;
        $order->delivery_type = $data['delivery_type'] ?? null;
        $order->delivery_method = $data['delivery_method'] ?? null;
        $order->delivery_comment = $data['delivery_comment'] ?? null;
        
        $order->number = 'IBT' . $now->format('Ymdhis');
        $order->delivery_address = [];
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
