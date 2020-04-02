<?php

namespace App\Observers\Delivery;

use App\Core\OrderSmsNotify;
use App\Models\Delivery\Delivery;
use App\Models\Delivery\DeliveryStatus;
use App\Models\Delivery\ShipmentStatus;
use App\Models\History\History;
use App\Models\History\HistoryType;
use App\Models\Order\OrderStatus;

/**
 * Class DeliveryObserver
 * @package App\Observers\Delivery
 */
class DeliveryObserver
{
    /**
     * Автоматическая установка статуса доставки для всех её отправлений
     */
    protected const STATUS_TO_SHIPMENTS = [
        DeliveryStatus::ON_POINT_IN => ShipmentStatus::ON_POINT_IN,
        DeliveryStatus::ARRIVED_AT_DESTINATION_CITY => ShipmentStatus::ARRIVED_AT_DESTINATION_CITY,
        DeliveryStatus::ON_POINT_OUT => ShipmentStatus::ON_POINT_OUT,
        DeliveryStatus::READY_FOR_RECIPIENT => ShipmentStatus::READY_FOR_RECIPIENT,
        DeliveryStatus::DELIVERING => ShipmentStatus::DELIVERING,
        DeliveryStatus::DONE => ShipmentStatus::DONE,
    ];

    /**
     * Автоматическая установка статуса для заказа, если все его доставки получили нужный статус
     */
    protected const STATUS_TO_ORDER = [
        DeliveryStatus::ASSEMBLING => OrderStatus::IN_PROCESSING,
        DeliveryStatus::SHIPPED => OrderStatus::TRANSFERRED_TO_DELIVERY,
        DeliveryStatus::ON_POINT_IN => OrderStatus::DELIVERING,
        DeliveryStatus::READY_FOR_RECIPIENT => OrderStatus::READY_FOR_RECIPIENT,
        DeliveryStatus::DONE => OrderStatus::DONE,
        DeliveryStatus::RETURNED => OrderStatus::RETURNED,
    ];

    /**
     * Handle the delivery "created" event.
     * @param  Delivery $delivery
     * @return void
     */
    public function created(Delivery $delivery)
    {
        History::saveEvent(HistoryType::TYPE_CREATE, $delivery->order, $delivery);
    }

    /**
     * Handle the delivery "updated" event.
     * @param  Delivery $delivery
     * @return void
     */
    public function updated(Delivery $delivery)
    {
        History::saveEvent(HistoryType::TYPE_UPDATE, $delivery->order, $delivery);

        $this->setStatusToShipments($delivery);
        $this->setStatusToOrder($delivery);
        $this->notifyIfShipped($delivery);
        $this->notifyIfReadyForRecipient($delivery);
    }

    /**
     * Handle the delivery "deleting" event.
     * @param  Delivery $delivery
     * @throws \Exception
     */
    public function deleting(Delivery $delivery)
    {
        History::saveEvent(HistoryType::TYPE_DELETE, $delivery->order, $delivery);
    }

    /**
     * Handle the delivery "saved" event.
     * @param  Delivery $delivery
     */
    public function saving(Delivery $delivery)
    {
        $this->setStatusAt($delivery);
        $this->setPaymentStatusAt($delivery);
        $this->setProblemAt($delivery);
        $this->setCanceledAt($delivery);
    }

    /**
     * Установить дату изменения статуса доставки.
     * @param  Delivery  $delivery
     */
    protected function setStatusAt(Delivery $delivery): void
    {
        if ($delivery->status != $delivery->getOriginal('status')) {
            $delivery->status_at = now();
        }
    }

    /**
     * Установить дату изменения статуса оплаты доставки.
     * @param  Delivery $delivery
     */
    protected function setPaymentStatusAt(Delivery $delivery): void
    {
        if ($delivery->payment_status != $delivery->getOriginal('payment_status')) {
            $delivery->payment_status_at = now();
        }
    }

    /**
     * Установить дату установки флага проблемной доставки
     * @param  Delivery $delivery
     */
    protected function setProblemAt(Delivery $delivery): void
    {
        if ($delivery->is_problem != $delivery->getOriginal('is_problem')) {
            $delivery->is_problem_at = now();
        }
    }

    /**
     * Установить дату отмены доставки
     * @param  Delivery $delivery
     */
    protected function setCanceledAt(Delivery $delivery): void
    {
        if ($delivery->is_canceled != $delivery->getOriginal('is_canceled')) {
            $delivery->is_canceled_at = now();
        }
    }

    /**
     * Установить статус доставки всем отправлениям
     * @param  Delivery $delivery
     */
    protected function setStatusToShipments(Delivery $delivery): void
    {
        if (isset(self::STATUS_TO_SHIPMENTS[$delivery->status]) && $delivery->status != $delivery->getOriginal('status')) {
            $delivery->loadMissing('shipments');
            foreach ($delivery->shipments as $shipment) {
                $shipment->status = self::STATUS_TO_SHIPMENTS[$delivery->status];
                $shipment->save();
            }
        }
    }

    /**
     * Автоматическая установка статуса для заказа, если все его доставки получили нужный статус
     * @param  Delivery $delivery
     */
    protected function setStatusToOrder(Delivery $delivery): void
    {
        if (isset(self::STATUS_TO_ORDER[$delivery->status]) && $delivery->status != $delivery->getOriginal('status')) {
            $order = $delivery->order;
            if ($order->status == self::STATUS_TO_ORDER[$delivery->status]) {
                return;
            }

            $allDeliveriesHasStatus = true;
            foreach ($order->deliveries as $orderDelivery) {
                if ($delivery->status == DeliveryStatus::READY_FOR_RECIPIENT) {
                    if ($orderDelivery->status == $delivery->status) {
                        $allDeliveriesHasStatus = false;
                        break;
                    }
                } else {
                    if ($orderDelivery->status < $delivery->status) {
                        $allDeliveriesHasStatus = false;
                        break;
                    }
                }
            }

            if ($allDeliveriesHasStatus) {
                $order->status = self::STATUS_TO_ORDER[$delivery->status];
                $order->save();
            }
        }
    }

    protected function notifyIfShipped(Delivery $delivery)
    {
        $oldStatus = $delivery->getOriginal('status');
        $newStatus = $delivery->status;
        if ($oldStatus != $newStatus) {
            if ($newStatus == DeliveryStatus::SHIPPED) {
                OrderSmsNotify::deliveryShipped($delivery);
            }
        }
    }

    protected function notifyIfReadyForRecipient(Delivery $delivery)
    {
        $oldStatus = $delivery->getOriginal('status');
        $newStatus = $delivery->status;
        if ($oldStatus != $newStatus) {
            if ($newStatus == DeliveryStatus::READY_FOR_RECIPIENT) {
                OrderSmsNotify::deliveryReadyForRecipient($delivery);
            }
        }
    }
}
