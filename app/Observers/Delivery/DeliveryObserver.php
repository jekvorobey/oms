<?php

namespace App\Observers\Delivery;

use App\Core\OrderSmsNotify;
use App\Models\Delivery\Delivery;
use App\Models\Delivery\DeliveryStatus;
use App\Models\Delivery\ShipmentStatus;
use App\Models\History\History;
use App\Models\History\HistoryType;
use App\Models\Order\OrderStatus;
use App\Services\DeliveryService;
use App\Services\OrderService;
use Greensight\Customer\Services\CustomerService\CustomerService;
use Greensight\Logistics\Dto\Lists\DeliveryMethod;
use Greensight\Message\Services\ServiceNotificationService\ServiceNotificationService;

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
        /**
         * Замечание по статусам "Ожидается отмена" и "Возвращена":
         * сейчас клиент может отказаться только от всей доставки целеком, а не от какой-то её части
         */
        DeliveryStatus::CANCELLATION_EXPECTED => ShipmentStatus::CANCELLATION_EXPECTED,
        DeliveryStatus::RETURNED => ShipmentStatus::RETURNED,
    ];

    /**
     * Автоматическая установка статуса для заказа, если все его доставки получили нужный статус
     */
    protected const STATUS_TO_ORDER = [
        DeliveryStatus::ASSEMBLING => OrderStatus::IN_PROCESSING,
        DeliveryStatus::SHIPPED => OrderStatus::TRANSFERRED_TO_DELIVERY,
        DeliveryStatus::ON_POINT_IN => OrderStatus::DELIVERING,
        DeliveryStatus::ARRIVED_AT_DESTINATION_CITY => OrderStatus::DELIVERING,
        DeliveryStatus::ON_POINT_OUT => OrderStatus::DELIVERING,
        DeliveryStatus::READY_FOR_RECIPIENT => OrderStatus::READY_FOR_RECIPIENT,
        DeliveryStatus::DELIVERING => OrderStatus::DELIVERING,
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
     * @param  Delivery  $delivery
     * @return void
     * @throws \Exception
     */
    public function updated(Delivery $delivery)
    {
        History::saveEvent(HistoryType::TYPE_UPDATE, $delivery->order, $delivery);

        $this->setStatusToShipments($delivery);
        $this->setIsCanceledToShipments($delivery);
        $this->setStatusToOrder($delivery);
        $this->setIsCanceledToOrder($delivery);
        // $this->notifyIfShipped($delivery);
        // $this->notifyIfReadyForRecipient($delivery);
        $this->sendNotification($delivery);
    }

    protected function sendNotification(Delivery $delivery)
    {
        $notificationService = app(ServiceNotificationService::class);
        $customerService = app(CustomerService::class);

        $customer = $customerService->customers(
            $customerService->newQuery()
                ->setFilter('id', '=', $delivery->order->customer_id)
        )->first()->user_id;

        if($delivery->status != $delivery->getOriginal('status')) {
            $notificationService->send(
                $customer,
                $this->createNotificationType(
                    $delivery->status,
                    $delivery->delivery_method == DeliveryMethod::METHOD_PICKUP
                ),
                [
                    'DELIVERY_DATE' => $delivery->delivery_at->toDateString(),
                    'DELIVERY_TIME' => $delivery->delivery_at->toTimeString(),
                    'PART_PRICE' => $delivery->cost,
                ]
            );
        }

        $order_id = $delivery->order->id;
        $link_order = sprintf("%s/profile/orders/%d", config('app.showcase_host'), $delivery->order->id);

        if(isset($delivery->getChanges()['delivery_address'])) {
            $notificationService->send(
                $customer,
                'servisnyeizmenenie_zakaza_adres_dostavki',
                [
                    'ORDER_ID' => $order_id,
                    'LINK_ORDER' => $link_order
                ]
            );
        }

        if($delivery->receiver_name != $delivery->getOriginal('receiver_name')) {
            $notificationService->send(
                $customer,
                'servisnyeizmenenie_zakaza_poluchatel_dostavki',
                [
                    'ORDER_ID' => $order_id,
                    'LINK_ORDER' => $link_order
                ]
            );
        }

        if($delivery->delivery_time_end != $delivery->getOriginal('delivery_time_end')) {
            $notificationService->send(
                $customer,
                'servisnyeizmenenie_zakaza_data_dostavki',
                [
                    'ORDER_ID' => $order_id,
                    'LINK_ORDER' => $link_order
                ]
            );
        }
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
     * Установить флаг отмены всем отправлениям
     * @param  Delivery $delivery
     * @throws \Exception
     */
    protected function setIsCanceledToShipments(Delivery $delivery): void
    {
        if ($delivery->is_canceled && $delivery->is_canceled != $delivery->getOriginal('is_canceled')) {
            $delivery->loadMissing('shipments');
            /** @var DeliveryService $deliveryService */
            $deliveryService = resolve(DeliveryService::class);
            foreach ($delivery->shipments as $shipment) {
                $deliveryService->cancelShipment($shipment);
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
                /**
                 * Для статуса доставки "Находится в Пункте Выдачи" проверяем,
                 * что все доставки заказа находятся строго в этом статусе,
                 * тогда устанавливаем статус заказа "Находится в Пункте Выдачи",
                 * иначе статус заказа не меняется
                 */
                if ($delivery->status == DeliveryStatus::READY_FOR_RECIPIENT) {
                    if ($orderDelivery->status != $delivery->status) {
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

    /**
     * Автоматическая установка флага отмены для заказа, если все его доставки отменены
     * @param  Delivery  $delivery
     * @throws \Exception
     */
    protected function setIsCanceledToOrder(Delivery $delivery): void
    {
        if ($delivery->is_canceled && $delivery->is_canceled != $delivery->getOriginal('is_canceled')) {
            $order = $delivery->order;
            if ($order->is_canceled) {
                return;
            }

            $allDeliveriesIsCanceled = true;
            foreach ($order->deliveries as $orderDelivery) {
                if (!$orderDelivery->is_canceled) {
                    $allDeliveriesIsCanceled = false;
                    break;
                }
            }

            if ($allDeliveriesIsCanceled) {
                /** @var OrderService $orderService */
                $orderService = resolve(OrderService::class);
                $orderService->cancel($order);
            }
        }
    }

    /**
     * @param  Delivery  $delivery
     */
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

    /**
     * @param  Delivery  $delivery
     */
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

    protected function createNotificationType(int $status, bool $postomat)
    {
        $type = $this->statusToType($status);

        $type .= '_bez_konsolidatsii';

        if($postomat) {
            $type .= '_pvzpostamat';
        } else {
            $type .= '_kurer';
        }

        return $type;
    }

    protected function statusToType(int $status)
    {
        switch ($status) {
            case DeliveryStatus::ON_POINT_IN:
                return 'status_dostavkiv_protsesse_dostavki';
            case DeliveryStatus::READY_FOR_RECIPIENT:
                return 'status_dostavkinakhoditsya_v_punkte_vydachi';
            case DeliveryStatus::DONE:
                return 'status_dostavkipoluchena';
            case DeliveryStatus::CANCELLATION_EXPECTED:
                return 'status_dostavkiotmenena';
            case DeliveryStatus::RETURN_EXPECTED_FROM_CUSTOMER:
                return 'status_dostavkiv_protsesse_vozvrata';
            case DeliveryStatus::RETURNED:
                return 'status_dostavkivozvrashchena';
            case DeliveryStatus::PRE_ORDER:
                return 'status_dostavkipredzakaz_ozhidaem_postupleniya_tovara';
        }
    }
}