<?php

namespace App\Services;

use App\Models\Order\Order;
use App\Models\Order\OrderStatus;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentStatus;
use Carbon\Carbon;

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
        $payment->status = PaymentStatus::PAID;
        $payment->payed_at = Carbon::now();

        return $payment->save();
    }

    /**
     * Обновить статус оплаты заказа в соотвествии со статусами оплат
     * @param Order $order
     */
    public function refreshPaymentStatus(Order $order): void
    {
        $order->refresh();
        $all = $order->payments->count();
        $statuses = [];
        foreach ($order->payments as $payment) {
            $statuses[$payment->status] = isset($statuses[$payment->status]) ? $statuses[$payment->status] + 1 : 1;
        }

        if ($this->allIs($statuses, $all, PaymentStatus::PAID)) {
            $this->setPaymentStatusPaid($order);
        } elseif ($this->atLeastOne($statuses, PaymentStatus::TIMEOUT) &&
            !$this->atLeastOne($statuses, PaymentStatus::PAID)) {
            $this->setPaymentStatusTimeout($order, false);
            try {
                $this->cancel($order);
            } catch (\Exception $e) {
            }
        }
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
     * Установить статус оплаты заказа на "Оплачено"
     * @param Order $order
     * @param  bool  $save
     * @return bool
     */
    public function setPaymentStatusPaid(Order $order, bool $save = true): bool
    {
        return $this->setPaymentStatus($order, PaymentStatus::PAID, $save);
    }

    /**
     * Установить статус оплаты заказа на "Просрочено"
     * @param Order $order
     * @param  bool  $save
     * @return bool
     */
    public function setPaymentStatusTimeout(Order $order, bool $save = true): bool
    {
        return $this->setPaymentStatus($order, PaymentStatus::TIMEOUT, $save);
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

    /**
     * @param  array  $statuses
     * @param  int  $count
     * @param  int  $status
     * @return bool
     */
    protected function allIs(array $statuses, int $count, int $status): bool
    {
        return ($statuses[$status] ?? 0) == $count;
    }

    /**
     * @param  array  $statuses
     * @param  int  $status
     * @return bool
     */
    protected function atLeastOne(array $statuses, int $status): bool
    {
        return ($statuses[$status] ?? 0) > 0;
    }
}
