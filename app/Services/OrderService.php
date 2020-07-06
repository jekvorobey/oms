<?php

namespace App\Services;

use App\Models\Order\Order;
use App\Models\Order\OrderStatus;
use App\Models\Payment\Payment;
use App\Services\PaymentService\PaymentService;

/**
 * Класс-бизнес логики по работе с заказами (без чекаута и доставки)
 * Class OrderService
 * @package App\Services
 */
class OrderService
{
    /**
     * Получить объект заказа по его id
     * @param  int  $orderId
     * @return Order|null
     */
    public function getOrder(int $orderId): ?Order
    {
        return Order::find($orderId);
    }

    /**
     * Вручную оплатить заказ.
     * Примечание: оплата по заказам автоматически должна поступать от платежной системы!
     * @param  Order  $order
     * @return bool
     * @throws \Exception
     */
    public function pay(Order $order): bool
    {
        /** @var Payment $payment */
        $payment = $order->payments->first();
        if (!$payment) {
            throw new \Exception("Оплата для заказа не найдена");
        }
        /** @var PaymentService $paymentService */
        $paymentService = resolve(PaymentService::class);

        return $paymentService->pay($payment);
    }

    /**
     * Обновить статус оплаты заказа в соотвествии со статусами оплат
     * @param Order $order
     */
    public function refreshPaymentStatus(Order $order): void
    {
        $order->refresh();
        /** @var Payment $payment */
        $payment = $order->payments->last();
        if (!$payment) {
            logger('refreshPaymentStatus without payment', ['orderId' => $order->id]);
            return;
        }

        $this->setPaymentStatus($order, $payment->status, true);
    }

    /**
     * Отменить заказ
     * @param  Order  $order
     * @return bool
     * @throws \Exception
     */
    public function cancel(Order $order): bool
    {
        if ($order->status >= OrderStatus::DONE) {
            throw new \Exception('Заказ, начиная со статуса "Выполнен", нельзя отменить');
        }

        $order->is_canceled = true;

        return $order->save();
    }

    /**
     * Установить статус оплаты заказа
     * @param Order $order
     * @param  int  $status
     * @param  bool  $save
     * @return bool
     */
    protected function setPaymentStatus(Order $order, int $status, bool $save = true): bool
    {
        $order->payment_status = $status;

        return $save ? $order->save() : true;
    }

    /**
     * Пометить заказ как проблемный
     * @param Order $order
     * @return bool
     */
    public function markAsProblem(Order $order): bool
    {
        $order->is_problem = true;

        return $order->save();
    }

    /**
     * Пометить заказ как непроблемный, если все его отправления непроблемные
     * @param Order $order
     * @return bool
     */
    public function markAsNonProblem(Order $order): bool
    {
        $order->loadMissing('deliveries.shipments');

        $isAllShipmentsOk = true;
        foreach ($order->deliveries as $delivery) {
            foreach ($delivery->shipments as $shipment) {
                if ($shipment->is_problem) {
                    $isAllShipmentsOk = false;
                    break 2;
                }
            }
        }

        $order->is_problem = !$isAllShipmentsOk;

        return $order->save();
    }
}
