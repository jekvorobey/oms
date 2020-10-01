<?php

namespace App\Observers\Delivery;

use App\Core\OrderSmsNotify;
use App\Models\Basket\BasketItem;
use App\Models\Delivery\Delivery;
use App\Models\Delivery\DeliveryStatus;
use App\Models\Delivery\DeliveryType;
use App\Models\Delivery\Shipment;
use App\Models\Delivery\ShipmentItem;
use App\Models\Delivery\ShipmentStatus;
use App\Models\History\History;
use App\Models\History\HistoryType;
use App\Models\Order\OrderStatus;
use App\Observers\Order\OrderObserver;
use App\Services\DeliveryService;
use App\Services\OrderService;
use Carbon\Carbon;
use Greensight\Customer\Services\CustomerService\CustomerService;
use Greensight\Logistics\Dto\Lists\DeliveryMethod;
use Greensight\Logistics\Services\ListsService\ListsService;
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
        try {
            $notificationService = app(ServiceNotificationService::class);
            $customerService = app(CustomerService::class);

            $customerRaw = optional($customerService->customers(
                $customerService->newQuery()
                    ->setFilter('id', '=', $delivery->order->customer_id)
            )->first());

            $customer = $customerRaw->user_id;

            if ($delivery->status != $delivery->getOriginal('status')) {
                $notificationService->send(
                    $customer,
                    $this->createNotificationType(
                        $delivery->status,
                        $delivery->delivery_method == DeliveryMethod::METHOD_PICKUP,
                    ),
                    (function () use ($delivery) {
                        switch ($delivery->status) {
                            case DeliveryStatus::DONE:
                            case DeliveryStatus::RETURNED:
                                return app(OrderObserver::class)->generateNotificationVariables($delivery->order, null, $delivery);
                        }

                        return [
                            'DELIVERY_DATE' => Carbon::parse($delivery->pdd)->toDateString(),
                            'DELIVERY_TIME' => (function () use ($delivery) {
                                $time = Carbon::parse($delivery->pdd);

                                if($time->isMidnight()) {
                                    return '';
                                }

                                return $time->toTimeString();
                            })(),
                            'PART_PRICE' => $delivery->cost,
                        ];
                    })()
                );
            }

            $order_id = $delivery->order->id;
            $link_order = sprintf("%s/profile/orders/%d", config('app.showcase_host'), $delivery->order->id);

            $user = $delivery->order->getUser();

            if (isset($delivery->getChanges()['delivery_address']) && $customer
                && array_diff($delivery->delivery_address, json_decode($delivery->getOriginal('delivery_address'), true)) != array_diff(json_decode($delivery->getOriginal('delivery_address'), true), $delivery->delivery_address)
            ) {
                $notificationService->send(
                    $customer,
                    'servisnyeizmenenie_zakaza_adres_dostavki',
                    $this->makeArray($delivery, sprintf('
                    %s, здравствуйте.
                    Ваш заказ №%s изменен.

                    Адрес доставки изменен на %s
                    ',
                    app(OrderObserver::class)->parseName($user, $delivery->order),
                    $delivery->order->number,
                    $delivery->formDeliveryAddressString($delivery->delivery_address)))
                );
            }

            if ($delivery->receiver_name != $delivery->getOriginal('receiver_name')) {
                $notificationService->send(
                    $customer,
                    'servisnyeizmenenie_zakaza_poluchatel_dostavki',
                    $this->makeArray($delivery, sprintf('
                    %s, здравствуйте.
                    Ваш заказ №%s изменен.

                    Получатель изменен: %s
                    ',
                    app(OrderObserver::class)->parseName($user, $delivery->order),
                    $delivery->order->number,
                    $delivery->receiver_name))
                );
            }

            if (Carbon::parse($delivery->pdd)->diffInDays(Carbon::parse($delivery->getOriginal('pdd'))) != 0) {
                $notificationService->send(
                    $customer,
                    'servisnyeizmenenie_zakaza_data_dostavki',
                    $this->makeArray($delivery, sprintf('
                    %s, здравствуйте.
                    Ваш заказ №%s изменен.

                    Дата доставки изменена на: %s
                    ',
                    app(OrderObserver::class)->parseName($user, $delivery->order),
                    $delivery->order->number,
                    $this->getDeliveryDate($delivery)))
                );
            }
        } catch (\Exception $e) {
            logger($e->getMessage(), $e->getTrace());
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

    protected function makeArray(Delivery $delivery, string $text)
    {
        $link_order = sprintf("%s/profile/orders/%d", config('app.showcase_host'), $delivery->order->id);
        $user = $delivery->order->getUser();

        $shipments = $delivery
            ->shipments
            ->map(function (Shipment $shipment) {
                return [
                    'date' => app(OrderObserver::class)->formatDeliveryDate($shipment->delivery),
                    'products' => $shipment
                        ->items
                        ->map(function (ShipmentItem $item) {
                            return $item->basketItem;
                        })
                        ->map(function (BasketItem $item) {
                            return [
                                'name' => $item->name,
                                'count' => (int) $item->qty,
                                'price' => (int) $item->price,
                                'image' => $item->getItemMedia()[0] ?? ''
                            ];
                        })
                        ->toArray()
                ];
            });

        return [
            'ORDER_ID' => $delivery->order->number,
            'LINK_ORDER' => $link_order,
            'CUSTOMER_NAME' => $user->first_name,
            'DELIVERY_ADDRESS' => $delivery->formDeliveryAddressString($delivery->delivery_address),
            'DELIVERY_TYPE' => DeliveryType::all()[$delivery->order->delivery_type]->name,
            'DELIVERY_DATE' => Carbon::parse($delivery->pdd)->locale('ru')->isoFormat('D MMMM, dddd'),
            'DELIVERY_TIME' => sprintf('с %s до %s', $delivery->delivery_time_start, $delivery->delivery_time_end),
            'FULL_NAME' => $delivery->receiver_name,
            'ORDER_CONTACT_NUMBER' => $delivery->receiver_phone,
            'ORDER_TEXT' => optional(optional($delivery->order)->comment)->text ?? '',
            'title' => sprintf('ЗАКАЗ %s ИЗМЕНЕН', $delivery->order->number),
            'text' => $text,
            'button' => [],
            'params' => [
                'Получатель' => app(OrderObserver::class)->parseName($user, $delivery->order),
                'Телефон' => OrderObserver::formatNumber($delivery->order->customerPhone()),
                'Сумма заказа' => sprintf('%s ₽', (int) $delivery->order->cost),
                'Получение' => DeliveryMethod::methodById($delivery->delivery_method)->name,
                'Дата доставки' => app(OrderObserver::class)->formatDeliveryDate($delivery),
                'Адрес доставки' => (function () use ($delivery) {
                    $points = app(ListsService::class);
                    if($delivery->delivery_method == DeliveryMethod::METHOD_PICKUP) {
                        return $delivery->formDeliveryAddressString($points->points(
                            $points->newQuery()
                                ->setFilter('id', $delivery->point_id)
                        )->first()->address);
                    }

                    return $delivery->formDeliveryAddressString($delivery->delivery_address ?? []);
                })()
            ],
            'shipments' => $shipments->toArray(),
            'delivery_price' => (int) $delivery->order->delivery_cost,
            'delivery_method' => DeliveryMethod::methodById($delivery->delivery_method)->name,
            'total_price' => (int) $delivery->order->price,
            'finisher_text' => sprintf(
                'Узнать статус выполнения заказа можно в <a href="%s">Личном кабинете</a>',
                sprintf("%s/profile", config('app.showcase_host'))
            ),
        ];
    }

    protected function getDeliveryDate(Delivery $delivery)
    {
        return sprintf(
            '%s %s',
            Carbon::parse($delivery->pdd)->locale('ru')->isoFormat('D MMMM, dddd'),
            sprintf('с %s до %s', $delivery->delivery_time_start, $delivery->delivery_time_end)
        );
    }
}