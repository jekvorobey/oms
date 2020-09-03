<?php

namespace App\Observers\Order;

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
use App\Models\Order\Order;
use App\Models\Order\OrderStatus;
use App\Models\Payment\PaymentStatus;
use App\Services\DeliveryService;
use App\Services\OrderService;
use Cms\Dto\OptionDto;
use Cms\Services\OptionService\OptionService;
use Greensight\CommonMsa\Services\AuthService\UserService;
use Greensight\Customer\Services\CustomerService\CustomerService;
use Greensight\Logistics\Dto\Lists\DeliveryMethod;
use Greensight\Message\Services\ServiceNotificationService\ServiceNotificationService;

/**
 * Class OrderObserver
 * @package App\Observers\Order
 */
class OrderObserver
{
    /**
     * @var array - автоматическая установка статусов для всех дочерних доставок и отправлений заказа
     */
    protected const STATUS_TO_CHILDREN = [
        OrderStatus::AWAITING_CHECK => [
            'deliveriesStatusTo' => DeliveryStatus::AWAITING_CHECK,
            'shipmentsStatusTo' => ShipmentStatus::AWAITING_CHECK,
        ],
        OrderStatus::CHECKING => [
            'deliveriesStatusTo' => DeliveryStatus::CHECKING,
            'shipmentsStatusTo' => ShipmentStatus::CHECKING,
        ],
        OrderStatus::AWAITING_CONFIRMATION => [
            'deliveriesStatusTo' => DeliveryStatus::AWAITING_CONFIRMATION,
            'shipmentsStatusTo' => ShipmentStatus::AWAITING_CONFIRMATION,
        ],
    ];

    /**
     * Handle the order "created" event.
     * @param  Order  $order
     * @return void
     */
    public function created(Order $order)
    {
        History::saveEvent(HistoryType::TYPE_CREATE, $order, $order);

        $order->basket->is_belongs_to_order = true;
        $order->basket->save();

        $this->sendCreatedNotification($order);
    }

    /**
     * Handle the order "updated" event.
     * @param  Order  $order
     * @return void
     * @throws \Exception
     */
    public function updated(Order $order)
    {
        History::saveEvent(HistoryType::TYPE_UPDATE, $order, $order);

        $this->setPaymentStatusToChildren($order);
        $this->setIsCanceledToChildren($order);
        // $this->notifyIfOrderPaid($order);
        $this->commitPaymentIfOrderDelivered($order);
        $this->setStatusToChildren($order);
        $this->returnTickets($order);
        $this->sendNotification($order);
        $this->sendTicketsEmail($order);
    }

    protected function sendCreatedNotification(Order $order)
    {
        $notificationService = app(ServiceNotificationService::class);
        $customerService = app(CustomerService::class);

        $user_id = $customerService
            ->customers(
                $customerService
                    ->newQuery()
                    ->setFilter('id', '=', $order->customer_id)
            )
            ->first()
            ->user_id;

        $this->sendStatusNotification($notificationService, $order, $user_id);
    }

    protected function sendNotification(Order $order)
    {
        $notificationService = app(ServiceNotificationService::class);
        $customerService = app(CustomerService::class);

        $user_id = $customerService
            ->customers(
                $customerService
                    ->newQuery()
                    ->setFilter('id', '=', $order->customer_id)
            )
            ->first()
            ->user_id;

        if($order->payment_status != $order->getOriginal('payment_status')) {
            foreach($order->deliveries as $delivery) {
                $notificationService->send(
                    $user_id,
                    $this->createPaymentNotificationType(
                        $order->payment_status,
                        $order->delivery_type === DeliveryType::TYPE_CONSOLIDATION,
                        $delivery->delivery_method === DeliveryMethod::METHOD_PICKUP
                    ),
                    $this->generateNotificationVariables($order)
                );
            }
        }

        if($order->status != $order->getOriginal('status')) {
            $this->sendStatusNotification($notificationService, $order, $user_id);
        }

        if(($order->is_canceled != $order->getOriginal('is_canceled')) && $order->is_canceled) {
            foreach($order->deliveries as $delivery) {
                $notificationService->send(
                    $user_id,
                    $this->createCancelledNotificationType(
                        $order->delivery_type === DeliveryType::TYPE_CONSOLIDATION,
                        $delivery->delivery_method === DeliveryMethod::METHOD_PICKUP
                    ),
                    $this->generateNotificationVariables($order)
                );
            }
        }

        // if(($order->is_problem != $order->getOriginal('is_problem')) && $order->is_problem) {
        //     foreach($order->deliveries as $delivery) {
        //         $notificationService->send(
        //             $user_id,
        //             'klientstatus_zakaza_problemnyy',
        //             $this->generateNotificationVariables($order)
        //         );
        //     }
        // }
    }

    protected function sendStatusNotification(ServiceNotificationService $notificationService, Order $order, int $user_id)
    {
        foreach($order->deliveries as $delivery) {
            $notificationService->send(
                $user_id,
                $this->createNotificationType(
                    $order->status,
                    $order->delivery_type === DeliveryType::TYPE_CONSOLIDATION,
                    $delivery->delivery_method === DeliveryMethod::METHOD_PICKUP
                ),
                $this->generateNotificationVariables($order)
            );
        }
    }

    /**
     * Handle the order "saving" event.
     * @param  Order  $order
     * @return void
     */
    public function saving(Order $order)
    {
        $this->setPaymentStatusAt($order);
        $this->setProblemAt($order);
        $this->setCanceledAt($order);
        $this->setAwaitingCheckStatus($order);
        $this->setAwaitingConfirmationStatus($order);

        //Данная команда должна быть в самом низу перед всеми $this->set*Status()
        $this->setStatusAt($order);
    }

    /**
     * Handle the order "deleting" event.
     * @param  Order  $order
     * @throws \Exception
     */
    public function deleting(Order $order)
    {
        History::saveEvent(HistoryType::TYPE_DELETE, $order, $order);

        //todo Поправить удаления связанных сущностей
        if ($order->basket) {
            $order->basket->delete();
        }
        foreach ($order->deliveries as $delivery) {
            $delivery->delete();
        }
    }

    /**
     * Установить дату изменения статуса заказа.
     * @param  Order $order
     */
    protected function setStatusAt(Order $order): void
    {
        if ($order->status != $order->getOriginal('status')) {
            $order->status_at = now();
        }
    }

    /**
     * Установить дату изменения статуса оплаты заказа.
     * @param  Order $order
     */
    protected function setPaymentStatusAt(Order $order): void
    {
        if ($order->payment_status != $order->getOriginal('payment_status')) {
            $order->payment_status_at = now();
        }
    }

    /**
     * Установить статус оплаты заказа всем доставкам и отправлениями заказа.
     * @param  Order $order
     */
    protected function setPaymentStatusToChildren(Order $order): void
    {
        if ($order->payment_status != $order->getOriginal('payment_status')) {
            $order->loadMissing('deliveries.shipments');
            foreach ($order->deliveries as $delivery) {
                $delivery->payment_status = $order->payment_status;
                $delivery->save();

                foreach ($delivery->shipments as $shipment) {
                    $shipment->payment_status = $order->payment_status;
                    $shipment->save();
                }
            }
        }
    }

    /**
     * Установить флаг отмены всем доставкам и отправлениями заказа
     * @param  Order  $order
     * @throws \Exception
     */
    protected function setIsCanceledToChildren(Order $order): void
    {
        if ($order->is_canceled && $order->is_canceled != $order->getOriginal('is_canceled')) {
            $order->loadMissing('deliveries.shipments');
            /** @var DeliveryService $deliveryService */
            $deliveryService = resolve(DeliveryService::class);
            foreach ($order->deliveries as $delivery) {
                $deliveryService->cancelDelivery($delivery);

                foreach ($delivery->shipments as $shipment) {
                    $deliveryService->cancelShipment($shipment);
                }
            }
        }
    }

    /**
     * Установить дату установки флага проблемного заказа
     * @param  Order $order
     */
    protected function setProblemAt(Order $order): void
    {
        if ($order->is_problem != $order->getOriginal('is_problem')) {
            $order->is_problem_at = now();
        }
    }

    /**
     * Установить дату отмены заказа
     * @param  Order $order
     */
    protected function setCanceledAt(Order $order): void
    {
        if ($order->is_canceled != $order->getOriginal('is_canceled')) {
            $order->is_canceled_at = now();
        }
    }

    /**
     * @param Order $order
     */
    private function notifyIfOrderPaid(Order $order): void
    {
        $oldPaymentStatus = $order->getOriginal('payment_status');
        $newPaymentStatus = $order->payment_status;
        if ($oldPaymentStatus != $newPaymentStatus) {
            if ($newPaymentStatus == PaymentStatus::PAID) {
                OrderSmsNotify::payed($order);
            }
        }
    }

    /**
     * @param  Order  $order
     */
    private function commitPaymentIfOrderDelivered(Order $order): void
    {
        $oldStatus = $order->getOriginal('status');
        $newStatus = $order->status;
        if ($newStatus == OrderStatus::DONE && $newStatus != $oldStatus) {
            foreach ($order->payments as $payment) {
                if ($payment->status == PaymentStatus::HOLD) {
                    $payment->commitHolded();
                }
            }
        }
    }

    /**
     * Переводим в статус "Ожидает проверки АОЗ" из статуса "Оформлен",
     * если установлен флаг "Заказ требует проверки (is_require_check)"
     * и заказ может быть обработан.
     * @param  Order  $order
     */
    protected function setAwaitingCheckStatus(Order $order): void
    {
        if ($order->status == OrderStatus::CREATED && $order->is_require_check && $order->canBeProcessed()) {
            $order->status = OrderStatus::AWAITING_CHECK;
        }
    }

    /**
     * Переводим в статус "Ожидает подтверждения Мерчантом" из статуса "Оформлен",
     * если НЕ установлен флаг "Заказ требует проверки (is_require_check)"
     * и заказ может быть обработан.
     * @param  Order  $order
     */
    protected function setAwaitingConfirmationStatus(Order $order): void
    {
        if ($order->status == OrderStatus::CREATED && !$order->is_require_check && $order->canBeProcessed()) {
            $order->status = OrderStatus::AWAITING_CONFIRMATION;
        }
    }

    /**
     * Установить статус заказа всем доставкам и отправлениями.
     * @param  Order $order
     */
    protected function setStatusToChildren(Order $order): void
    {
        if (isset(self::STATUS_TO_CHILDREN[$order->status]) && $order->status != $order->getOriginal('status')) {
            $order->loadMissing('deliveries.shipments');
            foreach ($order->deliveries as $delivery) {
                $delivery->status = self::STATUS_TO_CHILDREN[$order->status]['deliveriesStatusTo'];
                $delivery->save();

                foreach ($delivery->shipments as $shipment) {
                    $shipment->status = self::STATUS_TO_CHILDREN[$order->status]['shipmentsStatusTo'];
                    $shipment->save();
                }
            }
        }
    }

    /**
     * Вернуть остатки по билетам.
     * @param  Order $order
     */
    protected function returnTickets(Order $order): void
    {
        if ($order->payment_status != $order->getOriginal('payment_status') && $order->payment_status == PaymentStatus::TIMEOUT) {
            /** @var OrderService $orderService */
            $orderService = resolve(OrderService::class);
            $orderService->returnTickets(collect($order));
        }
    }

    protected function createPaymentNotificationType(int $payment_status, bool $consolidation, bool $postomat)
    {
        switch ($payment_status) {
            case PaymentStatus::NOT_PAID:
                return $this->appendTypeModifiers('status_zakazaozhidaet_oplaty', $consolidation, $postomat);
            case PaymentStatus::PAID:
                return $this->appendTypeModifiers('status_zakazaoplachen', $consolidation, $postomat);
            default:
                return '';
        }
    }

    protected function createNotificationType(int $orderStatus, bool $consolidation, bool $postomat)
    {
        $slug = $this->intoStringStatus($orderStatus);

        if($slug) {
            return $this->appendTypeModifiers($slug, $consolidation, $postomat);
        } 
        
        return '';
    }

    protected function intoStringStatus(int $orderStatus)
    {
        switch ($orderStatus) {
            case OrderStatus::PRE_ORDER: 
                return 'status_zakazapredzakaz_ozhidaem_postupleniya_tovara';
            case OrderStatus::CREATED:
                return 'status_zakazaoformlen';
            case OrderStatus::DELIVERING:
                return 'status_zakazav_protsesse_dostavki';
            case OrderStatus::READY_FOR_RECIPIENT:
                return 'status_zakazanakhoditsya_v_punkte_vydachi';
            case OrderStatus::DONE:
                return 'status_zakazadostavlen';
            case OrderStatus::RETURNED:
                return 'status_zakazavozvrashchen';
            default:
                return '';
        }
    }

    protected function appendTypeModifiers(string $slug, bool $consolidation, bool $postomat)
    {
        if($consolidation) {
            $slug .= '_pri_konsolidatsii';
        } else {
            $slug .= '_bez_konsolidatsii';
        }

        if($postomat) {
            $slug .= '_pvzpostamat';
        } else {
            $slug .= '_kurer';
        }

        return $slug;
    }

    protected function createCancelledNotificationType(bool $consolidation, bool $postomat)
    {
        return $this->appendTypeModifiers('status_zakazaotmenen', $consolidation, $postomat);
    }

    public function generateNotificationVariables(Order $order, bool $awaiting_payment = false)
    {
        $customerService = app(CustomerService::class);
        $userService = app(UserService::class);
        // $optionService = app(OptionService::class);

        $customer = $customerService->customers($customerService->newQuery()->setFilter('id', '=', $order->customer_id))->first();
        $user = $userService->users($userService->newQuery()->setFilter('id', '=', $customer->user_id))->first();

        $payment = $order->payments->first();

        $link = optional(optional($payment)->paymentSystem())->paymentLink($payment);
        [$title, $text] = (function () use ($order, $awaiting_payment) {
            if($awaiting_payment) {
                return [
                    '%s, ВАШ ЗАКАЗ ОЖИДАЕТ ОПЛАТЫ',
                    sprintf('Ваш заказ %s ожидает оплаты. Чтобы перейти
                    <br>к оплате нажмите на кнопку "Оплатить заказ"', $order->id)
                ];
            }

            switch ($order->status) {
                case OrderStatus::CREATED:
                    return ['%s, СПАСИБО ЗА ЗАКАЗ', sprintf('Ваш заказ %s успешно оформлен и принят в обработку', $order->id)];
                case OrderStatus::DELIVERING:
                    return ['%s, ВАШ ЗАКАЗ В ПУТИ', 'Ваш заказ подтвержден и передан в транспортную компанию. <br>Ожидайте звонка курьера.'];
                case OrderStatus::READY_FOR_RECIPIENT:
                    return ['%s, ВАШ ЗАКАЗ ОЖИДАЕТ ВАС', 'Ваш заказ поступил в пункт самовывоза. Вы можете забрать свою покупку в течении 3-х дней'];
                case OrderStatus::DONE:
                    return [
                        '%s, ' . sprintf("ВАШ ЗАКАЗ %s ВЫПОЛНЕН", $order->id),
                        'Спасибо что выбрали нас! Надеемся что процесс покупки доставил
                        <br>вам исключительно положительные эмоции.
                        <br><br>Пожалуйста, оставьте свой отзыв о покупках, чтобы помочь нам стать
                        <br>еще лучше и удобнее'
                    ];
                case OrderStatus::RETURNED:
                    return [
                        '%s, ВАШ ЗАКАЗ ОТМЕНЕН',
                        sprintf('Вы отменили ваш заказ %s. Товар вернулся на склад.
                        <br>Пожалуйста, напишите нам, почему вы не смогли забрать заказ.', $order->id)
                    ];
            }
        })();

        $button = (function () use ($order, $awaiting_payment, $link) {
            if($awaiting_payment) {
                return [
                    'text' => 'ОПЛАТИТЬ ЗАКАЗ',
                    'link' => $link
                ];
            }

            if($order->status == OrderStatus::RETURNED) {
                return [
                    'text' => 'НАПИСАТЬ НАМ',
                    'link' => sprintf("%s/feedback", config('app.showcase_host'))
                ];
            }

            return [];
        })();

        $deliveryAddress = $order
            ->deliveries
            ->unique('delivery_address')
            ->map(function (Delivery $delivery) {
                return sprintf(
                    "%s%s%s%s",
                    $delivery->delivery_address['street'] . ', ',
                    $delivery->delivery_address['house'] . ', ',
                    $delivery->delivery_address['city'] . ', ',
                    $delivery->delivery_address['post_index']
                );
            })
            ->join('<br>');

        $deliveryDate = $order
            ->deliveries
            ->map(function (Delivery $delivery) {
                return $this->formatDeliveryDate($delivery);
            })
            ->unique()
            ->join('<br>');

        $shipments = $order
            ->deliveries
            ->map(function (Delivery $delivery) {
                return $delivery->shipments;
            })
            ->flatten()
            ->map(function (Shipment $shipment) {
                return [
                    'date' => $this->formatDeliveryDate($shipment->delivery),
                    'products' => $shipment
                        ->items
                        ->map(function (ShipmentItem $item) {
                            return $item->basketItem;
                        })
                        ->map(function (BasketItem $item) {
                            return [
                                'name' => $item->name,
                                'count' => $item->qty,
                                'price' => $item->price,
                            ];
                        })
                        ->toArray()
                ];
            });

        return [
            'title' => sprintf($title, mb_strtoupper($user->first_name)),
            'text' => $text,
            'button' => $button,
            'params' => [
                'Получатель' => $user->first_name,
                'Телефон' => $order->customerPhone(),
                'Сумма заказа' => $order->cost,
                'Получение' => DeliveryType::all()[$order->delivery_type]->name,
                'Дата доставки' => $deliveryDate,
                'Адрес доставки' => $deliveryAddress
            ],
            'shipments' => $shipments->toArray(),
            'delivery_price' => $order->delivery_cost,
            'total_price' => $order->cost,
            'finisher_text' => sprintf(
                'Узнать статус выполнения заказа можно в <a href="%s">Личном кабинете</a>',
                sprintf("%s/profile", config('app.showcase_host'))
            ),
        ];
    }
    /**
     * Отправить билеты на мастер-классы на почту покупателю заказа и всем участникам.
     * @param  Order  $order
     * @throws \Throwable
     */
    protected function sendTicketsEmail(Order $order): void
    {
        if ($order->payment_status != $order->getOriginal('payment_status') && $order->isPaid()) {
            /** @var OrderService $orderService */
            $orderService = resolve(OrderService::class);
            $orderService->sendTicketsEmail($order);
        }
    }

    protected function formatDeliveryDate(Delivery $delivery)
    {
        $date = $delivery->delivery_at->locale('ru')->isoFormat('D MMMM, dddd');
        if($delivery->delivery_time_start) {
            $date .= sprintf(", с %s до %s", $delivery->delivery_time_start, $delivery->delivery_time_end);
        }
        return $date;
    }
}
