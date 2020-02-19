<?php

namespace App\Services;

use App\Models\Basket\Basket;
use App\Models\Delivery\ShipmentStatus;
use App\Models\Order\Order;
use App\Models\Order\OrderStatus;
use App\Models\Payment\PaymentStatus;

/**
 * Класс-бизнес логики по работе с заказами (без чекаута и доставки)
 * Class OrderService
 * @package App\Services
 */
class OrderService
{
    /** @var array|Order[] - кэш объектов заказов с id в качестве ключа */
    public static $ordersCached = [];

    public function createOrder(): Order
    {
        //todo
    }

    /**
     * Получить объект заказа по его id
     * @param  int  $orderId
     * @return Order|null
     */
    public function getOrder(int $orderId): ?Order
    {
        if (!isset(static::$ordersCached[$orderId])) {
            static::$ordersCached[$orderId] = Order::find($orderId);
        }

        return static::$ordersCached[$orderId];
    }

    /**
     * Получить корзину заказа
     * @param  int  $orderId
     * @return Basket|null
     */
    public function getBasket(int $orderId): ?Basket
    {
        $order = $this->getOrder($orderId);
        if (is_null($order)) {
            return null;
        }

        return $order->basket;
    }

    /**
     * Обновить статус оплаты заказа в соотвествии со статусами оплат
     * @param int $orderId
     */
    public function refreshPaymentStatus(int $orderId): void
    {
        $order = $this->getOrder($orderId);
        if (is_null($order)) {
            return;
        }

        $order->refresh();
        $all = $order->payments->count();
        $statuses = [];
        foreach ($order->payments as $payment) {
            $statuses[$payment->status] = isset($statuses[$payment->status]) ? $statuses[$payment->status] + 1 : 1;
        }

        if ($this->allIs($statuses, $all, PaymentStatus::PAID)) {
            $this->setPaymentStatusPaid($orderId);
        } elseif ($this->atLeastOne($statuses, PaymentStatus::TIMEOUT) &&
            !$this->atLeastOne($statuses, PaymentStatus::PAID)) {
            $this->setPaymentStatusTimeout($orderId, false);
            $this->cancel($orderId);
        }
    }

    /**
     * Отменить заказ
     * @param  int  $orderId
     * @param  bool  $save
     * @return bool
     */
    public function cancel(int $orderId, bool $save = true): bool
    {
        $order = $this->getOrder($orderId);
        if (is_null($order)) {
            return false;
        }

        $order->setStatus(OrderStatus::STATUS_CANCEL);

        return $save ? $order->save() : true;
    }

    /**
     * Установить статус оплаты заказа на "Оплачено"
     * @param int $orderId
     * @param  bool  $save
     * @return bool
     */
    public function setPaymentStatusPaid(int $orderId, bool $save = true): bool
    {
        return $this->setPaymentStatus($orderId, PaymentStatus::PAID, $save);
    }

    /**
     * Установить статус оплаты заказа на "Просрочено"
     * @param int $orderId
     * @param  bool  $save
     * @return bool
     */
    public function setPaymentStatusTimeout(int $orderId, bool $save = true): bool
    {
        return $this->setPaymentStatus($orderId, PaymentStatus::TIMEOUT, $save);
    }

    /**
     * Установить статус оплаты заказа
     * @param int $orderId
     * @param  int  $status
     * @param  bool  $save
     * @return bool
     */
    protected function setPaymentStatus(int $orderId, int $status, bool $save = true): bool
    {
        $order = $this->getOrder($orderId);
        if (is_null($order)) {
            return false;
        }

        $order->setPaymentStatus($status);

        return $save ? $order->save() : true;
    }

    /**
     * Пометить заказ как проблемный
     * @param int $orderId
     * @param bool $save
     * @return bool
     */
    public function markAsProblem(int $orderId, bool $save = true): bool
    {
        $order = $this->getOrder($orderId);
        if (is_null($order)) {
            return false;
        }

        $order->is_problem = true;

        return $save ? $order->save() : true;
    }

    /**
     * Пометить заказ как непроблемный, если все его отправления непроблемные
     * @param int $orderId
     * @param bool $save
     * @return bool
     */
    public function markAsNonProblem(int $orderId, bool $save = true): bool
    {
        $order = $this->getOrder($orderId);
        $order->load('deliveries.shipments');
        if (is_null($order)) {
            return false;
        }

        $isAllShipmentsOk = true;
        foreach ($order->deliveries as $delivery) {
            foreach ($delivery->shipments as $shipment) {
                if (in_array($shipment->status, [
                    ShipmentStatus::STATUS_ASSEMBLING_PROBLEM, ShipmentStatus::STATUS_TIMEOUT
                ])) {
                    $isAllShipmentsOk = false;
                    break 2;
                }
            }
        }

        $order->is_problem = !$isAllShipmentsOk;

        return $save && !$order->is_problem ? $order->save() : true;
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
