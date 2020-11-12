<?php

namespace App\Observers\Order;

use App\Core\OrderSmsNotify;
use App\Models\Basket\Basket;
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
use App\Models\Order\OrderBonus;
use App\Models\Order\OrderStatus;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentStatus;
use App\Services\DeliveryService;
use App\Services\OrderService;
use App\Services\TicketNotifierService;
use Cms\Dto\OptionDto;
use Cms\Services\OptionService\OptionService;
use Greensight\CommonMsa\Dto\UserDto;
use Greensight\CommonMsa\Services\AuthService\UserService;
use Greensight\Customer\Services\CustomerService\CustomerService;
use Greensight\Logistics\Dto\Lists\DeliveryMethod;
use Greensight\Logistics\Services\ListsService\ListsService;
use Greensight\Message\Services\ServiceNotificationService\ServiceNotificationService;
use GuzzleHttp\Client;
use Illuminate\Support\Arr;

/**
 * Class OrderObserver
 * @package App\Observers\Order
 */
class OrderObserver
{
    const OVERRIDE_CANCEL = 1;
    const OVERRIDE_AWAITING_PAYMENT = 2;
    const OVERRIDE_SUCCESSFUL_PAYMENT = 3;
    const OVERRIDE_SUCCESS = 4;

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
     * @throws \Throwable
     */
    public function updated(Order $order)
    {
        History::saveEvent(HistoryType::TYPE_UPDATE, $order, $order);

        $this->setPaymentStatusToChildren($order);
        $this->setIsCanceledToChildren($order);
        // $this->notifyIfOrderPaid($order);
        $this->commitPaymentIfOrderDelivered($order);
        $this->setStatusToChildren($order);
        $this->sendNotification($order);
    }

    protected function sendCreatedNotification(Order $order)
    {
        try {
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
            $notificationService->sendToAdmin('aozzakazzakaz_oformlen');
        } catch (\Exception $e) {
            logger($e->getMessage(), $e->getTrace());
        }
    }

    protected function sendNotification(Order $order)
    {
        try {
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

            if($order->status != $order->getOriginal('status') && $order->status == OrderStatus::DONE) {
                $this->sendStatusNotification($notificationService, $order, $user_id);
            }

            $sent_notification = false;

            if ($order->payment_status != $order->getOriginal('payment_status')) {
                if($this->shouldSendPaidNotification($order)) {
                    if($order->type == Basket::TYPE_MASTER) {
                        app(TicketNotifierService::class)->notify($order);
                    }

                    $this->sendStatusNotification($notificationService, $order, $user_id, static::OVERRIDE_SUCCESS);
                    $sent_notification = true;
                }

                if($this->shouldSendPaidNotification($order) || $order->payment_status == PaymentStatus::TIMEOUT || $order->payment_status == PaymentStatus::WAITING) {
                    $notificationService->send(
                        $user_id,
                        $this->createPaymentNotificationType(
                            $order->payment_status,
                            $order->delivery_type === DeliveryType::TYPE_CONSOLIDATION,
                            $order->deliveries()->first()->delivery_method === DeliveryMethod::METHOD_PICKUP
                        ),
                        $this->generateNotificationVariables($order, (function () use ($order) {
                            switch ($order->payment_status) {
                                case PaymentStatus::TIMEOUT:
                                case PaymentStatus::WAITING:
                                    return static::OVERRIDE_AWAITING_PAYMENT;
                                case PaymentStatus::PAID:
                                case PaymentStatus::HOLD:
                                    return static::OVERRIDE_SUCCESSFUL_PAYMENT;
                            }

                            return null;
                        })())
                    );
                }
            }

            if (
                $order->status != $order->getOriginal('status')
                && !in_array($order->status, [OrderStatus::CREATED, OrderStatus::AWAITING_CONFIRMATION, OrderStatus::DONE])
                && !$sent_notification
            ) {
                $this->sendStatusNotification($notificationService, $order, $user_id);
            }

            if (($order->is_canceled != $order->getOriginal('is_canceled')) && $order->is_canceled) {
                $notificationService->send(
                    $user_id,
                    $this->createCancelledNotificationType(
                        $order->delivery_type === DeliveryType::TYPE_CONSOLIDATION,
                        $order->deliveries()->first()->delivery_method === DeliveryMethod::METHOD_PICKUP
                    ),
                    $this->generateNotificationVariables($order, static::OVERRIDE_CANCEL)
                );
                $notificationService->sendToAdmin('aozzakazzakaz_otmenen');
            } else {
                $notificationService->sendToAdmin('aozzakazzakaz_izmenen');
            }
        } catch (\Exception $e) {
            logger($e->getMessage(), $e->getTrace());
        }
    }

    protected function sendStatusNotification(ServiceNotificationService $notificationService, Order $order, int $user_id, int $override = null)
    {
        if($order->deliveries()->exists()) {
            $notificationService->send(
                $user_id,
                $this->createNotificationType(
                    $order->status,
                    $order->delivery_type === DeliveryType::TYPE_CONSOLIDATION,
                    $order->deliveries()->first()->delivery_method === DeliveryMethod::METHOD_PICKUP,
                    $override
                ),
                $this->generateNotificationVariables($order, $override)
            );
        }
    }

    /**
     * Handle the order "saving" event.
     * @param  Order  $order
     * @return void
     * @throws \Throwable
     */
    public function saving(Order $order)
    {
        $this->setPaymentStatusAt($order);
        $this->setProblemAt($order);
        $this->setCanceledAt($order);
        $this->setAwaitingCheckStatus($order);
        $this->setAwaitingConfirmationStatus($order);
        $this->sendTicketsEmail($order);
        $this->returnTickets($order);

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
            if ($order->type == Basket::TYPE_PRODUCT) {
                $order->status = OrderStatus::AWAITING_CONFIRMATION;
            }
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
            //Не сохраняем данные по заказу внутри метода возврата билетов, иначе будет цикл
            $orderService->returnTickets(collect()->push($order), false);
        }
    }

    protected function createPaymentNotificationType(int $payment_status, bool $consolidation, bool $postomat)
    {
        switch ($payment_status) {
            case PaymentStatus::TIMEOUT:
            case PaymentStatus::WAITING:
                return $this->appendTypeModifiers('status_zakazaozhidaet_oplaty', $consolidation, $postomat);
            case PaymentStatus::PAID:
                return $this->appendTypeModifiers('status_zakazaoplachen', $consolidation, $postomat);
            case PaymentStatus::HOLD:
                return $this->appendTypeModifiers('status_zakazaoplachen', $consolidation, $postomat);
            default:
                return '';
        }
    }

    protected function createNotificationType(int $orderStatus, bool $consolidation, bool $postomat, int $override = null)
    {
        if($override == static::OVERRIDE_SUCCESS) {
            $orderStatus = OrderStatus::CREATED;
        }

        // if($orderStatus == OrderStatus::READY_FOR_RECIPIENT) {
        //     $postomat = true;
        // }

        // if($orderStatus == OrderStatus::DONE) {
        //     $consolidation = true;
        // }

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
            // case OrderStatus::CREATED:
            //     return 'status_zakazaoformlen';
            // case OrderStatus::AWAITING_CONFIRMATION:
            //     return 'status_zakazaoformlen';
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

    public function generateNotificationVariables(Order $order, int $override = null, Delivery $override_delivery = null)
    {
        $customerService = app(CustomerService::class);
        $userService = app(UserService::class);

        $customer = $customerService->customers($customerService->newQuery()->setFilter('id', '=', $order->customer_id))->first();
        $user = $userService->users($userService->newQuery()->setFilter('id', '=', $customer->user_id))->first();

        /** @var Payment $payment */
        $payment = $order->payments->first();

        $link = optional(optional($payment)->paymentSystem())->paymentLink($payment);
        [$title, $text] = (function () use ($order, $override, $user, $override_delivery) {
            if($override_delivery) {
                $bonus = optional($order->bonuses->first());

                if($override_delivery->status == DeliveryStatus::DONE) {
                    if($bonus->bonus) {
                        return [
                            sprintf('ВАМ НАЧИСЛЕН: %s ₽ БОНУС ЗА ЗАКАЗ', $bonus->bonus ?? 0),
                            sprintf('%s, здравствуйте! 
                            Вам начислен: %s ₽ бонус за заказ %s. Бонусы будут действительны до %s. Потратить их можно на следующую покупку. 
                            
                            Нам важно ваше мнение!
                            Мы будем признательны, если вы поделитесь своим мнением о купленном товаре!
                            Оставить отзыв можно, пройдя по ссылке: %s',
                            $user->first_name,
                            $bonus->bonus ?? 0,
                            $order->number,
                            $bonus->getExpirationDate() ?? 'не указано',
                            config('app.showcase_host'))
                        ];
                    }

                    return [
                        sprintf('%s, ВАШ ЗАКАЗ %s ВЫПОЛНЕН', mb_strtoupper($user->first_name), $order->number),
                        sprintf('Спасибо, что выбрали iBT.ru! Надеемся, что процесс покупки доставил вам исключительно положительные эмоции.

                        Нам важно ваше мнение! 
                        Будем признательны, если поделитесь своим мнением о купленном товаре. 
                        Оставить отзыв можно по ссылке: %s', config('app.showcase_host'))
                    ];
                }
                
                if($override_delivery->status == DeliveryStatus::CANCELLATION_EXPECTED) {
                    return [
                        sprintf('ЗАКАЗ %s ОТМЕНЕН, ВОЗВРАТ ПРОИЗВЕДЕН', $order->number),
                        sprintf('Заказ %s на сумму %s р. отменен.

                        Возврат денежных средств на сумму %s р. произведен. Срок зависит от вашего банка.
                        Если у вас возникли сложности с заказом - сообщите нам. 
                        Мы сделаем все возможное, чтобы вам помочь!',
                        $order->number,
                        $order->orderReturns->first()->price,
                        $order->orderReturns->first()->price)
                    ];
                }

                if($override_delivery->status == DeliveryStatus::RETURN_EXPECTED_FROM_CUSTOMER) {
                    return [
                        sprintf('ЗАЯВКА НА ВОЗВРАТ ПО ЗАКАЗУ %s ОФОРМЛЕНА', $order->number),
                        sprintf('Вы успешно оформили заявку на возврат товара из заказа %s %s.
                        Вам необходимо передать возвращаемый товар в курьерскую службу согласно условиям возврата товара %s
                        
                        Если у вас возникли сложности с заказом - сообщите нам. 
                        Мы сделаем все возможное, чтобы вам помочь!',
                        $order->number,
                        sprintf("%s/profile/orders/%d", config('app.showcase_host'), $order->id),
                        sprintf('%s/purchase-returns', config('app.showcase_host')))
                    ];
                }

                if($override_delivery->status == DeliveryStatus::RETURNED) {
                    return [
                        sprintf('ВОЗВРАТ ПО ЗАКАЗУ %s ПРОИЗВЕДЕН', $order->number),
                        sprintf('Возврат по заказу %s в размере %s р. произведен. 
                        Срок возврата денежных средств зависит от вашего банка.
                        
                        Если у вас возникли сложности с заказом - сообщите нам. 
                        Мы сделаем все возможное, чтобы вам помочь!',
                        $order->number,
                        (int) $order->price)
                    ];
                }

                if($override_delivery->status == DeliveryStatus::ON_POINT_IN) {
                    return [
                        sprintf('ЗАКАЗ %s ПЕРЕДАН В СЛУЖБУ ДОСТАВКИ', $order->number),
                        sprintf('Заказ №%s на сумму %s р. передано в службу доставки. 
                        Статус заказа вы можете отслеживать в личном кабинете на сайте: %s',
                        $order->number,
                        (int) $order->price,
                        sprintf("%s/profile/orders/%d", config('app.showcase_host'), $order->id))
                    ];
                }

                if($override_delivery->status == DeliveryStatus::READY_FOR_RECIPIENT) {
                    return [
                        sprintf('ЗАКАЗ %s ОЖИДАЕТ В ПУНКТЕ ВЫДАЧИ!', $order->number),
                        sprintf('Заказ №%s на сумму %s р. ожидает вас в пункте выдачи по адресу: %s. 
                        ВНИМАНИЕ! Получить заказ может только контактное лицо, указанное в заказе, с паспортом.',
                        $order->number,
                        (int) $order->price,
                        $override_delivery->getDeliveryAddressString())
                    ];
                }
            }

            if($override == static::OVERRIDE_SUCCESS) {
                return ['%s, СПАСИБО ЗА ЗАКАЗ', sprintf('Ваш заказ %s успешно оформлен и принят в обработку', $order->number)];
            }

            if($override == static::OVERRIDE_CANCEL) {
                return [
                    '%s, ВАШ ЗАКАЗ ОТМЕНЕН',
                    sprintf('Вы отменили ваш заказ %s. Товар вернулся на склад.
                    <br>Пожалуйста, напишите нам, почему вы не смогли забрать заказ.', $order->number)
                ];
            }
            
            if($override == static::OVERRIDE_AWAITING_PAYMENT) {
                return [
                    '%s, ВАШ ЗАКАЗ ОЖИДАЕТ ОПЛАТЫ',
                    sprintf('Ваш заказ %s ожидает оплаты. Чтобы перейти
                    <br>к оплате нажмите на кнопку "Оплатить заказ"', $order->number)
                ];
            }

            if($override == static::OVERRIDE_SUCCESSFUL_PAYMENT) {
                return [
                    sprintf('ЗАКАЗ %s ОПЛАЧЕН И ПРИНЯТ В ОБРАБОТКУ!', $order->number),
                    sprintf('%s, заказ %s оплачен и принят в обработку.
                    Статус заказа вы можете отслеживать в личном кабинете на сайте: %s',
                    $user->first_name,
                    $order->number,
                    sprintf('%s/profile', config('app.showcase_host')))
                ];
            }

            switch ($order->status) {
                case OrderStatus::CREATED:
                case OrderStatus::AWAITING_CONFIRMATION:
                    return ['%s, СПАСИБО ЗА ЗАКАЗ', sprintf('Ваш заказ %s успешно оформлен и принят в обработку', $order->number)];
                case OrderStatus::DELIVERING:
                    return [
                        sprintf('ЗАКАЗ %s ПЕРЕДАН В СЛУЖБУ ДОСТАВКИ', $order->number),
                        sprintf('Заказ №%s на сумму %s р. передан в службу доставки. 
                        <br>Статус заказа вы можете отслеживать в личном кабинете на сайте: %s',
                        $order->number,
                        (int) $order->price,
                        sprintf('%s/profile', config('app.showcase_host')))
                    ];
                case OrderStatus::READY_FOR_RECIPIENT:
                    return ['%s, ВАШ ЗАКАЗ ОЖИДАЕТ ВАС', 'Ваш заказ поступил в пункт самовывоза. Вы можете забрать свою покупку в течении 3-х дней'];
                case OrderStatus::DONE:
                    return [
                        '%s, ' . sprintf("ВАШ ЗАКАЗ %s ВЫПОЛНЕН", $order->number),
                        'Спасибо что выбрали нас! Надеемся что процесс покупки доставил
                        <br>вам исключительно положительные эмоции.
                        <br><br>Пожалуйста, оставьте свой отзыв о покупках, чтобы помочь нам стать
                        <br>еще лучше и удобнее'
                    ];
                // case OrderStatus::RETURNED:
                //     return [
                //         '%s, ВАШ ЗАКАЗ ОТМЕНЕН',
                //         sprintf('Вы отменили ваш заказ %s. Товар вернулся на склад.
                //         <br>Пожалуйста, напишите нам, почему вы не смогли забрать заказ.', $order->number)
                //     ];
            }
        })();

        $button = (function () use ($link, $override) {
            if($override == static::OVERRIDE_AWAITING_PAYMENT) {
                return [
                    'text' => 'ОПЛАТИТЬ ЗАКАЗ',
                    'link' => $link
                ];
            }

            if($override == static::OVERRIDE_CANCEL) {
                return [
                    'text' => 'НАПИСАТЬ НАМ',
                    'link' => sprintf("%s/feedback", config('app.showcase_host'))
                ];
            }

            return [];
        })();

        /** @var ListsService */
        $points = app(ListsService::class);

        $deliveryAddress = $order
            ->deliveries
            ->unique('delivery_address')
            ->map(function (Delivery $delivery) use ($points) {
                if($delivery->delivery_method == DeliveryMethod::METHOD_PICKUP) {
                    return $delivery->formDeliveryAddressString($points->points(
                        $points->newQuery()
                            ->setFilter('id', $delivery->point_id)
                    )->first()->address);
                }

                return $delivery->formDeliveryAddressString($delivery->delivery_address ?? []);
            })
            ->join('<br>');

        if(empty($deliveryAddress)) {
            $deliveryAddress = 'ПВЗ';
        }

        $goods = $order->basket->items->map(function (BasketItem $item) {
            $deliveryMethodId = optional(optional(optional(optional($item)->shipmentItem)->shipment)->delivery)->delivery_method;
            return [
                'name' => $item->name,
                'price' => $item->price,
                'count' => $item->qty,
                'delivery' => $deliveryMethodId ? DeliveryMethod::methodById($deliveryMethodId)->name : null,
            ];
        });

        $deliveryDate = null;
        if($override_delivery) {
            $deliveryDate = $this->formatDeliveryDate($override_delivery);
        } else {
            $deliveryDate = $order
                ->deliveries
                ->map(function (Delivery $delivery) {
                    return $this->formatDeliveryDate($delivery);
                })
                ->unique()
                ->join('<br>');
        }

        $deliveryMethod = null;
        if($override_delivery) {
            $deliveryMethod = DeliveryMethod::methodById($override_delivery->delivery_method)->name;
        } else {
            $deliveryMethod = $order
                ->deliveries
                ->map(function (Delivery $delivery) {
                    return DeliveryMethod::methodById($delivery->delivery_method)->name;
                })
                ->unique()
                ->join('<br>');
        }

        $shipments = null;
        if($override_delivery) {
            $shipments = $override_delivery->shipments;
        } else {
            $shipments = $order
                ->deliveries
                ->map(function (Delivery $delivery) {
                    return $delivery->shipments;
                })
                ->flatten();
        }

        $shipments = $shipments
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
                                'count' => (int) $item->qty,
                                'price' => (int) $item->price,
                                'image' => $item->getItemMedia()[0] ?? ''
                            ];
                        })
                        ->toArray()
                ];
            })
            ->mapToGroups(function ($shipment) {
                return [$shipment['date'] => $shipment['products']];
            })
            ->map(function ($shipment) {
                return Arr::flatten($shipment, 1);
            })
            ->map(function ($products, $date) {
                return [
                    'date' => $date,
                    'products' => $products
                ];
            })
            ->values();

        return [
            'title' => sprintf($title, mb_strtoupper($this->parseName($user, $order))),
            'text' => $text,
            'button' => $button,
            'params' => [
                'Получатель' => $this->parseName($user, $order),
                'Телефон' => static::formatNumber($order->customerPhone()),
                'Сумма заказа' => sprintf('%s ₽', (int) $order->price),
                'Получение' => $deliveryMethod,
                'Дата доставки' => $deliveryDate,
                'Адрес доставки' => $deliveryAddress
            ],
            'shipments' => $shipments->toArray(),
            'delivery_price' => (int) $order->delivery_cost,
            'delivery_method' => $deliveryMethod,
            'total_price' => (int) $order->price,
            'finisher_text' => sprintf(
                'Узнать статус выполнения заказа можно в <a href="%s">Личном кабинете</a>',
                sprintf("%s/profile", config('app.showcase_host'))
            ),
            'ORDER_ID' => $order->number,
            'FULL_NAME' => sprintf('%s %s', $user->first_name, $user->last_name),
            'LINK_ACCOUNT' => (string) static::shortenLink(sprintf("%s/profile/orders/%d", config('app.showcase_host'), $order->id)),
            'LINK_PAY' => (string) static::shortenLink($link),
            'ORDER_DATE' => $order->created_at->toDateString(),
            'ORDER_TIME' => $order->created_at->toTimeString(),
            // 'DELIVERY_TYPE' => optional(DeliveryMethod::methodById(optional($order->deliveries->first())->delivery_method ?? 0))->name ?? '',
            'DELIVERY_TYPE' => (function () use ($order) {
                if(!$order->deliveries->first()) {
                    return '';
                }

                return DeliveryMethod::methodById($order->deliveries->first()->delivery_method)->name;
            })(),
            'DELIVERY_ADDRESS' => (function () use ($order) {
                /** @var Delivery */
                $delivery = $order->deliveries->first();
                return optional($delivery)
                    ->formDeliveryAddressString($delivery->delivery_address ?? []) ?? '';
            })(),
            'DELIVIRY_ADDRESS' => (function () use ($order) {
                /** @var Delivery */
                $delivery = $order->deliveries->first();
                return optional($delivery)
                    ->formDeliveryAddressString($delivery->delivery_address ?? []) ?? '';
            })(),
            'DELIVERY_DATE' => optional(optional($order
                ->deliveries
                ->first())
                ->delivery_at)
                ->toDateString() ?? '',
            'DELIVERY_TIME' => (function () use ($order) {
                $delivery = $order->deliveries->first();

                if($delivery == null || $delivery->delivery_at == null) {
                    return '';
                }

                if($delivery->delivery_at->isMidnight()) {
                    return '';
                }

                return $delivery->delivery_at->toTimeString();
            })(),
            'OPER_MODE' => (function () use ($order, $points) {
                $point_id = optional($order->deliveries->first())->point_id;

                if($point_id == null) {
                    return '';
                }

                return $points->points(
                    $points->newQuery()
                        ->setFilter('id', $point_id)
                )->first()->timetable;
            })(),
            'CALL_TK' => (function () use ($order, $points) {
                $point_id = optional($order->deliveries->first())->point_id;

                if($point_id == null) {
                    return app(OptionService::class)->get(OptionDto::KEY_ORGANIZATION_CARD_CONTACT_CENTRE_PHONE);
                }

                return $points->points(
                    $points->newQuery()
                        ->setFilter('id', $point_id)
                )->first()->phone;
            })(),
            'CUSTOMER_NAME' => $this->parseName($user, $order),
            'ORDER_CONTACT_NUMBER' => $order->number,
            'ORDER_TEXT' => optional($order->deliveries->first())->delivery_address['comment'] ?? '',
            'RETURN_REPRICE' => (int) $order->price,
            'NUMBER_BAL' => (function () use ($order) {
                return $order
                    ->bonuses
                    ->filter(function (OrderBonus $bonus) {
                        $bonus->status == OrderBonus::STATUS_ACTIVE;
                    })
                    ->sum(function (OrderBonus $bonus) {
                        return $bonus->bonus;
                    });
            })(),
            'DEADLINE_BAL' => (function () use ($order) {
                return optional($order
                    ->bonuses()
                    ->orderBy('valid_period')
                    ->first())
                    ->getExpirationDate() ?? 'неопределенного срока';
            })(),
            'goods' => $goods->all()
        ];
    }

    /**
     * Отправить билеты на мастер-классы на почту покупателю заказа и всем участникам.
     * @param  Order  $order
     * @throws \Throwable
     */
    protected function sendTicketsEmail(Order $order): void
    {
        if ($order->payment_status != $order->getOriginal('payment_status') && $order->isPaid() && $order->isPublicEventOrder()) {
            /** @var OrderService $orderService */
            $orderService = resolve(OrderService::class);
            $orderService->sendTicketsEmail($order);
            $order->status = OrderStatus::DONE;
        }
    }

    public function formatDeliveryDate(Delivery $delivery)
    {
        $date = $delivery->delivery_at->locale('ru')->isoFormat('D MMMM, dddd');
        if($delivery->delivery_time_start) {
            $date .= sprintf(", с %s до %s", $delivery->delivery_time_start, $delivery->delivery_time_end);
        }
        return $date;
    }

    public function parseName(UserDto $user, Order $order)
    {
        if(isset($user->first_name)) {
            return $user->first_name;
        }

        if(!$order->receiver_name) {
            return '';
        }

        $words = explode($order->receiver_name, ' ');

        if(isset($words[1])) {
            return $words[1];
        }

        return $words[0];
    }

    public static function formatNumber(string $number)
    {
        $number = substr($number, 1);
        return '+'.substr($number, 0, 1).' '.substr($number, 1, 3).' '.substr($number, 4, 3).'-'.substr($number, 7, 2).'-'.substr($number, 9, 2);
    }

    public static function shortenLink(?string $link)
    {
        if($link === null) {
            return '';
        }

        /** @var Client */
        $client = app(Client::class);

        return $client->request('GET', 'https://clck.ru/--', [
            'query' => [
                'url' => $link
            ]
        ])->getBody();
    }

    protected function shouldSendPaidNotification(Order $order)
    {
        $paid = ($order->payment_status == PaymentStatus::HOLD) || (
            $order->payment_status == PaymentStatus::PAID && $order->getOriginal('payment_status') != PaymentStatus::HOLD
        );

        $created = ($order->status == OrderStatus::CREATED) || ($order->status == OrderStatus::AWAITING_CONFIRMATION);

        return $paid && $created;
    }

    public function testSend()
    {
        $order = Order::find(1014);
        // $order = Order::query()
        //     ->whereNotNull('customer_id')
        //     ->where('status', '=', OrderStatus::CREATED)
        //     // ->whereNotIn('payment_status', [PaymentStatus::PAID, PaymentStatus::HOLD])
        //     ->whereDeliveryType(DeliveryType::TYPE_CONSOLIDATION)
        //     ->whereHas('deliveries', function ($q) {
        //         $q->where('delivery_method', DeliveryMethod::METHOD_DELIVERY);
        //     })
        //     ->whereDoesntHave('deliveries', function ($q) {
        //         $q->where('delivery_method', DeliveryMethod::METHOD_PICKUP);
        //     })
        //     ->latest()
        //     ->firstOrFail();

        $st = $order->status;
        $ps = $order->payment_status;

        $order->status = OrderStatus::DONE;
        $order->payment_status = PaymentStatus::PAID;

        $order->save();

        // $this->sendStatusNotification($notificationService, $order, $user_id);

        // $order->status = OrderStatus::TRANSFERRED_TO_DELIVERY;
        // $order->payment_status = PaymentStatus::PAID;

        // $order->save();

        dump("IGNORE FROM HERE");

        $order->status = $st;
        $order->payment_status = $ps;
        
        $order->save();
    }
}
