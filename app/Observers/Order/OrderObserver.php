<?php

namespace App\Observers\Order;

use App\Core\OrderSmsNotify;
use App\Models\Basket\Basket;
use App\Models\Basket\BasketItem;
use App\Models\Delivery\Delivery;
use App\Models\Delivery\DeliveryStatus;
use App\Models\Delivery\Shipment;
use App\Models\Delivery\ShipmentItem;
use App\Models\Order\Order;
use App\Models\Order\OrderStatus;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentStatus;
use App\Services\DeliveryService;
use App\Services\OrderService;
use App\Services\PaymentService\PaymentService;
use App\Services\ShipmentService;
use App\Services\TicketNotifierService;
use Cms\Dto\OptionDto;
use Cms\Services\OptionService\OptionService;
use Cms\Services\RedirectService\RedirectService;
use Exception;
use Greensight\CommonMsa\Dto\UserDto;
use Greensight\CommonMsa\Services\AuthService\UserService;
use Greensight\Customer\Services\CustomerService\CustomerService;
use Greensight\Logistics\Dto\Lists\DeliveryMethod;
use Greensight\Logistics\Services\ListsService\ListsService;
use Greensight\Message\Services\ServiceNotificationService\ServiceNotificationService;
use GuzzleHttp\Client;
use Illuminate\Support\Arr;
use Pim\Dto\Certificate\CertificateRequestDto;
use Pim\Dto\Certificate\CertificateRequestStatusDto;
use Pim\Services\CertificateService\CertificateService;
use Pim\Services\OfferService\OfferService;
use Pim\Services\ProductService\ProductService;
use Pim\Services\CategoryService\CategoryService;
use Throwable;

/**
 * Class OrderObserver
 * @package App\Observers\Order
 */
class OrderObserver
{
    public const OVERRIDE_CANCEL = 1;
    public const OVERRIDE_AWAITING_PAYMENT = 2;
    public const OVERRIDE_SUCCESSFUL_PAYMENT = 3;
    public const OVERRIDE_SUCCESS = 4;

    /**
     * Handle the order "created" event.
     * @return void
     */
    public function created(Order $order)
    {
        $order->number = $order->id + 1000000;
        Order::withoutEvents(fn() => $order->save());

        $order->basket->is_belongs_to_order = true;
        $order->basket->save();

        $this->sendCreatedNotification($order);
        $this->setOrderIdToCertificateRequest($order);
    }

    /**
     * Handle the order "updated" event.
     * @return void
     * @throws Throwable
     */
    public function updated(Order $order)
    {
        $this->setPaymentStatusToChildren($order);
        $this->setIsCanceledToChildren($order);
        // $this->setIsProblemToChildren($order);
        // $this->notifyIfOrderPaid($order);
        $this->commitPaymentIfOrderTransferredOrDelivered($order);
        $this->setStatusToChildren($order);
        if ($order->type != Basket::TYPE_CERTIFICATE) {
            $this->sendNotification($order);
        }
        $this->setPaymentStatusToCertificateRequest($order);
        $this->cancelBonuses($order);
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
        } catch (Throwable $e) {
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

            $statusWasChanged = $order->status != $order->getOriginal('status');

            if ($statusWasChanged && $order->status == OrderStatus::DONE) {
                $this->sendStatusNotification($notificationService, $order, $user_id);
            }

            $sent_notification = false;

            if ($order->payment_status != $order->getOriginal('payment_status')) {
                if ($this->shouldSendPaidNotification($order)) {
                    if ($order->type == Basket::TYPE_MASTER) {
                        app(TicketNotifierService::class)->notify($order);
                    }

                    $this->sendStatusNotification($notificationService, $order, $user_id, self::OVERRIDE_SUCCESS);
                    $sent_notification = true;
                }

                if (
                    $order->type !== Basket::TYPE_MASTER
                    && (
                        $this->shouldSendPaidNotification($order)
                        // || $order->payment_status == PaymentStatus::TIMEOUT
                        || ($order->payment_status == PaymentStatus::WAITING && !$order->is_postpaid)
                    )
                ) {
                    $delivery_method = !empty($order->deliveries()->first()->delivery_method) &&
                        $order->deliveries()->first()->delivery_method === DeliveryMethod::METHOD_PICKUP;
                    $notificationService->send(
                        $user_id,
                        $this->createPaymentNotificationType(
                            $order->payment_status,
                            $order->isConsolidatedDelivery(),
                            $delivery_method
                        ),
                        $this->generateNotificationVariables($order, (function () use ($order) {
                            switch ($order->payment_status) {
                                // case PaymentStatus::TIMEOUT:
                                case PaymentStatus::WAITING:
                                    return self::OVERRIDE_AWAITING_PAYMENT;
                                case PaymentStatus::PAID:
                                case PaymentStatus::HOLD:
                                    return self::OVERRIDE_SUCCESSFUL_PAYMENT;
                            }

                            return null;
                        })())
                    );
                } elseif ($order->payment_status === PaymentStatus::WAITING && $order->is_postpaid) {
                    $delivery_method = !empty($order->deliveries()->first()->delivery_method)
                        ? $order->deliveries()->first()->delivery_method === DeliveryMethod::METHOD_PICKUP
                        : false;
                    $notificationService->send(
                        $user_id,
                        $this->appendTypeModifiers('status_zakazaoformlen', $order->isConsolidatedDelivery(), $delivery_method),
                        $this->generateNotificationVariables($order, self::OVERRIDE_SUCCESS)
                    );
                }
            }

            if (
                $statusWasChanged
                && !in_array($order->status, [OrderStatus::CREATED, OrderStatus::AWAITING_CONFIRMATION, OrderStatus::DONE])
                && !$sent_notification
            ) {
                $this->sendStatusNotification($notificationService, $order, $user_id);
            }

            if ($order->is_canceled != $order->getOriginal('is_canceled') && $order->is_canceled) {
                $orderDelivery = $order->deliveries()->first();
                $notificationService->send(
                    $user_id,
                    $this->createCancelledNotificationType(
                        $order->isConsolidatedDelivery(),
                        $orderDelivery ? $orderDelivery->delivery_method === DeliveryMethod::METHOD_PICKUP : false,
                        $order->isPaid()
                    ),
                    $this->generateNotificationVariables($order, self::OVERRIDE_CANCEL)
                );
                $notificationService->sendToAdmin('aozzakazzakaz_otmenen');
            } else {
                $notificationService->sendToAdmin('aozzakazzakaz_izmenen');
            }
        } catch (Throwable $e) {
            report($e);
            logger($e->getMessage(), $e->getTrace());
        }
    }

    protected function sendStatusNotification(
        ServiceNotificationService $notificationService,
        Order $order,
        int $user_id,
        ?int $override = null
    ) {
        if ($order->deliveries()->exists()) {
            $notificationService->send(
                $user_id,
                $this->createNotificationType(
                    $order->status,
                    $order->isConsolidatedDelivery(),
                    $order->deliveries()->first()->delivery_method === DeliveryMethod::METHOD_PICKUP,
                    $override
                ),
                $this->generateNotificationVariables($order, $override)
            );
        }
    }

    /**
     * Handle the order "saving" event.
     * @return void
     * @throws Throwable
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
     * @throws Exception
     */
    public function deleting(Order $order)
    {
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
     */
    protected function setStatusAt(Order $order): void
    {
        if ($order->status != $order->getOriginal('status')) {
            $order->status_at = now();
        }
    }

    /**
     * Установить дату изменения статуса оплаты заказа.
     */
    protected function setPaymentStatusAt(Order $order): void
    {
        if ($order->payment_status != $order->getOriginal('payment_status')) {
            $order->payment_status_at = now();
        }
    }

    /**
     * Установить статус оплаты заказа всем доставкам и отправлениями заказа.
     */
    protected function setPaymentStatusToChildren(Order $order): void
    {
        if ($order->is_postpaid) {
            return;
        }

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
     * @throws Exception
     */
    protected function setIsCanceledToChildren(Order $order): void
    {
        if ($order->is_canceled && $order->is_canceled != $order->getOriginal('is_canceled')) {
            $order->loadMissing('deliveries.shipments');
            /** @var DeliveryService $deliveryService */
            $deliveryService = resolve(DeliveryService::class);
            foreach ($order->deliveries as $delivery) {
                $deliveryService->cancelDelivery($delivery, $order->return_reason_id);
            }
        }
    }

    /**
     * Установить флаг проблемы всем доставкам и отправлениями заказа
     * @throws Exception
     */
    protected function setIsProblemToChildren(Order $order): void
    {
        if ($order->is_problem && $order->is_problem != $order->getOriginal('is_problem')) {
            $order->loadMissing('deliveries.shipments');
            /** @var ShipmentService $shipmentService */
            $shipmentService = resolve(ShipmentService::class);
            foreach ($order->deliveries as $delivery) {
                foreach ($delivery->shipments as $shipment) {
                    $shipmentService->markAsProblemShipment($shipment);
                }
            }
        }
    }

    /**
     * Установить дату установки флага проблемного заказа
     */
    protected function setProblemAt(Order $order): void
    {
        if ($order->is_problem != $order->getOriginal('is_problem')) {
            $order->is_problem_at = now();
        }
    }

    /**
     * Установить дату отмены заказа
     */
    protected function setCanceledAt(Order $order): void
    {
        if ($order->is_canceled != $order->getOriginal('is_canceled')) {
            $order->is_canceled_at = now();
        }
    }

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
     * Списываем холдированные деньги у клиента,
     * когда мерчант подтвердил наличие товара и отдал курьеру (OrderStatus::TRANSFERRED_TO_DELIVERY)
     * или когда заказ доставлен (OrderStatus::DONE)
     */
    private function commitPaymentIfOrderTransferredOrDelivered(Order $order): void
    {
        /** @var Payment $payment */
        $payment = $order->payments->last();
        if (!$payment) {
            return;
        }

        if (in_array($order->status, [OrderStatus::TRANSFERRED_TO_DELIVERY, OrderStatus::DONE]) && $order->wasChanged('status')) {
            /** @var Payment $payment */
            $payment = $order->payments->last();

            $paymentService = new PaymentService();

            if ($payment->status == PaymentStatus::HOLD) {
                $paymentService->capture($payment);
            }

            if ($order->isProductOrder() && $order->status == OrderStatus::DONE) {
                $paymentService->sendIncomeFullPaymentReceipt($payment);
            }
        }
    }

    /**
     * Переводим в статус "Ожидает проверки АОЗ" из статуса "Оформлен",
     * если установлен флаг "Заказ требует проверки (is_require_check)"
     * и заказ может быть обработан.
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
     */
    protected function setStatusToChildren(Order $order): void
    {
        if (isset(OrderService::STATUS_TO_CHILDREN[$order->status]) && $order->status != $order->getOriginal('status')) {
            $order->loadMissing('deliveries.shipments');
            foreach ($order->deliveries as $delivery) {
                $delivery->status = OrderService::STATUS_TO_CHILDREN[$order->status]['deliveriesStatusTo'];
                $delivery->save();

                foreach ($delivery->shipments as $shipment) {
                    $shipment->status = OrderService::STATUS_TO_CHILDREN[$order->status]['shipmentsStatusTo'];
                    $shipment->save();
                }
            }
        }
    }

    /**
     * Вернуть остатки по билетам.
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

    /**
     * Отклонение начисленных бонусов за заказ
     */
    protected function cancelBonuses(Order $order): void
    {
        if ($order->is_canceled && $order->wasChanged('is_canceled')) {
            $customerService = resolve(CustomerService::class);
            $customerService->declineByOrder($order->customer_id, $order->id);

            foreach ($order->bonuses as $bonus) {
                $bonus->cancel();
            }
        }
    }

    protected function createPaymentNotificationType(int $payment_status, bool $consolidation, bool $postomat): string
    {
        switch ($payment_status) {
            // case PaymentStatus::TIMEOUT:
            case PaymentStatus::WAITING:
                return $this->appendTypeModifiers('status_zakazaozhidaet_oplaty', $consolidation, $postomat);
            case PaymentStatus::PAID:
            case PaymentStatus::HOLD:
                return $this->appendTypeModifiers('status_zakazaoplachen', $consolidation, $postomat);
            default:
                return '';
        }
    }

    protected function createNotificationType(
        int $orderStatus,
        bool $consolidation,
        bool $postomat,
        ?int $override = null
    ): string {
        if ($override == self::OVERRIDE_SUCCESS) {
            $orderStatus = OrderStatus::CREATED;
        }

        // if($orderStatus == OrderStatus::READY_FOR_RECIPIENT) {
        //     $postomat = true;
        // }

        // if($orderStatus == OrderStatus::DONE) {
        //     $consolidation = true;
        // }

        $slug = $this->intoStringStatus($orderStatus);

        if ($slug) {
            return $this->appendTypeModifiers($slug, $consolidation, $postomat);
        }

        return '';
    }

    protected function intoStringStatus(int $orderStatus): string
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

    protected function appendTypeModifiers(
        string $slug,
        bool $consolidation,
        bool $postomat,
        ?bool $isPaid = null
    ): string {
        if ($consolidation) {
            $slug .= '_pri_konsolidatsii';
        } else {
            $slug .= '_bez_konsolidatsii';
        }

        if ($postomat) {
            $slug .= '_pvzpostamat';
        } else {
            $slug .= '_kurer';
        }

        if ($isPaid !== null) {
            if ($isPaid === true) {
                $slug .= '_oplachen';
            } else {
                $slug .= '_ne_oplachen';
            }
        }

        return $slug;
    }

    protected function createCancelledNotificationType(bool $consolidation, bool $postomat, bool $isPaid): string
    {
        return $this->appendTypeModifiers('status_zakaza_otmenen', $consolidation, $postomat, $isPaid);
    }

    public function generateNotificationVariables(
        Order $order,
        ?int $override = null,
        ?Delivery $override_delivery = null,
        bool $delivery_canceled = false
    ) {
        /** @var CustomerService $customerService */
        $customerService = app(CustomerService::class);

        /** @var Payment $payment */
        $payment = $order->payments->first();

        $link = optional($payment)->payment_link;

        $button = (function () use ($link, $override) {
            if ($override == self::OVERRIDE_AWAITING_PAYMENT) {
                return [
                    'text' => 'ОПЛАТИТЬ ЗАКАЗ',
                    'link' => $link,
                ];
            }

            if ($override == self::OVERRIDE_CANCEL) {
                return [
                    'text' => 'НАПИСАТЬ НАМ',
                    'link' => sprintf('%s/feedback', config('app.showcase_host')),
                ];
            }

            return [];
        })();

        /** @var ListsService $points */
        $points = app(ListsService::class);

        $deliveryAddress = $order
            ->deliveries
            ->map(function (Delivery $delivery) use ($points) {
                if ($delivery->delivery_method == DeliveryMethod::METHOD_PICKUP) {
                    return $delivery->formDeliveryAddressString($points->points(
                        $points->newQuery()
                            ->setFilter('id', $delivery->point_id)
                    )->first()->address);
                }

                return $delivery->formDeliveryAddressString($delivery->delivery_address ?? []);
            })
            ->unique('delivery_address')
            ->join('<br>');

        /** @var UserService $userService */
        $userService = app(UserService::class);

        $customer = $customerService->customers($customerService->newQuery()->setFilter('id', '=', $order->customer_id))->first();
        /** @var UserDto $user */
        $user = $userService->users($userService->newQuery()->setFilter('id', '=', $customer->user_id))->first();

        $receiverFullName = $order->receiver_name ?: $order->deliveries->first()->receiver_name;
        $receiverPhone = $order->receiver_phone ?: str_replace(['(', ')', '-', ' '], '', $order->deliveries->first()->receiver_phone);

        if (empty($deliveryAddress)) {
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
        if ($override_delivery) {
            $deliveryDate['normal_length'] = $this->formatDeliveryDate($override_delivery);
            $deliveryDate['short'] = $this->formatDeliveryDate($override_delivery, true);
        } else {
            $deliveryDate['normal_length'] = $this->getFormattedDeliveryDatesByOrder($order);
            $deliveryDate['short'] = $this->getFormattedDeliveryDatesByOrder($order, true);
        }

        $deliveryMethod = null;
        if ($override_delivery) {
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
        if ($override_delivery && $override_delivery->status !== DeliveryStatus::DONE) {
            $shipments = $override_delivery->shipments;
        } else {
            $shipments = $order
                ->deliveries
                ->map(function (Delivery $delivery) {
                    return $delivery->shipments;
                })
                ->flatten();
        }

        $saved_shipments = $shipments;

        /** @var OfferService $offerService */
        $offerService = app(OfferService::class);
        /** @var ProductService $productService */
        $productService = app(ProductService::class);
        /** @var CategoryService $categoryService */
        $categoryService = app(CategoryService::class);
        /** @var RedirectService $redirectService */
        $redirectService = app(RedirectService::class);

        $shipments = $shipments
            ->map(function (Shipment $shipment) use ($order, $offerService, $productService, $categoryService) {
                return [
                    'date' => $this->formatDeliveryDate($shipment->delivery),
                    'products' => $shipment
                        ->items
                        ->map(function (ShipmentItem $item) {
                            return $item->basketItem;
                        })
                        ->map(function (BasketItem $item) use ($order, $offerService, $productService, $categoryService) {
                            $productItem = [
                                'name' => $item->name,
                                'count' => (int) $item->qty,
                                'price' => (int) $item->price,
                                'image' => $item->getItemMedia()[0] ?? '',
                            ];

                            if ($order->status === OrderStatus::DONE) {
                                $offer = $offerService->offers(
                                    $offerService->newQuery()
                                        ->setFilter('id', $item->offer_id)
                                )->first();

                                $product = $productService->products(
                                    $productService->newQuery()
                                        ->setFilter('id', $offer->product_id)
                                )->first();

                                $category = $categoryService->categories(
                                    $categoryService->newQuery()
                                        ->setFilter('id', $product->category_id)
                                )->first();

                                $productItem['button'] = [
                                    'link' => sprintf('%s/catalog/%s/%s#characteristics', config('app.showcase_host'), $category->code, $product->code),
                                    'text' => 'ОСТАВИТЬ ОТЗЫВ',
                                ];
                            }

                            return $productItem;
                        })
                        ->toArray(),
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
                    'products' => $products,
                ];
            })
            ->values();

        $part_price = 0;
        if (!empty($shipments) && !empty($shipments->toArray()[0])) {
            $products = $shipments->toArray()[0]['products'];
            foreach ($products as $product) {
                $part_price += $product['price'];
            }
        }

        $bonusInfo = $customerService->getBonusInfo($order->customer_id);

        $params = [];
        $withoutParams = false;
        $hideShipmentsDate = false;
        $receiverFullNameByParts = explode(' ', $receiverFullName);

        [$title, $text] = (function () use (
            $order,
            $override,
            $user,
            $override_delivery,
            $delivery_canceled,
            $part_price,
            $saved_shipments,
            &$params,
            $points,
            &$deliveryAddress,
            &$deliveryDate,
            &$withoutParams,
            &$hideShipmentsDate
        ) {
            if ($override_delivery) {
                // $bonus = optional($order->bonuses->first());

                if ($override_delivery->status == DeliveryStatus::DONE) {
                    // if($bonus->bonus) {
                    //     return [
                    //         sprintf('ВАМ НАЧИСЛЕН: %s ₽ БОНУС ЗА ЗАКАЗ', $bonus->bonus ?? 0),
                    //         sprintf('%s, здравствуйте!
                    //         Вам начислен: %s ₽ бонус за заказ %s. Бонусы будут действительны до %s. Потратить их можно на следующую покупку.

                    //         Нам важно ваше мнение!
                    //         Мы будем признательны, если вы поделитесь своим мнением о купленном товаре!
                    //         Оставить отзыв можно, пройдя по ссылке: %s',
                    //         $user->first_name,
                    //         $bonus->bonus ?? 0,
                    //         $order->number,
                    //         $bonus->getExpirationDate() ?? 'не указано',
                    //         config('app.showcase_host'))
                    //     ];
                    // }

                    $withoutParams = true;
                    $hideShipmentsDate = true;

                    return [
                        sprintf('%s, ВАШ ЗАКАЗ %s ВЫПОЛНЕН', mb_strtoupper($user->first_name), $order->number),
                        'Спасибо что выбрали нас! Надеемся что процесс покупки доставил
                        <br>вам исключительно положительные эмоции.
                        <br><br>Пожалуйста, оставьте свой отзыв о покупках, чтобы помочь нам стать
                        <br>еще лучше и удобнее',
                    ];
                }

                if ($override_delivery->status == DeliveryStatus::CANCELLATION_EXPECTED || $delivery_canceled) {
                    return [
                        sprintf('ЗАКАЗ %s ОТМЕНЕН, ВОЗВРАТ ПРОИЗВЕДЕН', $order->number),
                        sprintf(
                            'Заказ %s на сумму %s р. отменен.

                        Возврат денежных средств на сумму %s р. произведен. Срок зависит от вашего банка.
                        Если у вас возникли сложности с заказом - сообщите нам.
                        Мы сделаем все возможное, чтобы вам помочь!',
                            $order->number,
                            $delivery_canceled ? (int) $override_delivery->order->price : $order->orderReturns->first()->price,
                            $delivery_canceled ? (int) $override_delivery->order->price : $order->orderReturns->first()->price
                        ),
                    ];
                }

                if ($override_delivery->status == DeliveryStatus::RETURN_EXPECTED_FROM_CUSTOMER) {
                    return [
                        sprintf('ЗАЯВКА НА ВОЗВРАТ ПО ЗАКАЗУ %s ОФОРМЛЕНА', $order->number),
                        sprintf(
                            'Вы успешно оформили заявку на возврат товара из заказа %s %s.
                        Вам необходимо передать возвращаемый товар в курьерскую службу согласно условиям возврата товара %s

                        Если у вас возникли сложности с заказом - сообщите нам.
                        Мы сделаем все возможное, чтобы вам помочь!',
                            $order->number,
                            sprintf('%s/profile/orders/%d', config('app.showcase_host'), $order->id),
                            sprintf('%s/purchase-returns', config('app.showcase_host'))
                        ),
                    ];
                }

                if ($override_delivery->status == DeliveryStatus::RETURNED) {
                    return [
                        sprintf('ВОЗВРАТ ПО ЗАКАЗУ %s ПРОИЗВЕДЕН', $order->number),
                        sprintf(
                            'Возврат по заказу %s в размере %s р. произведен.
                        Срок возврата денежных средств зависит от вашего банка.

                        Если у вас возникли сложности с заказом - сообщите нам.
                        Мы сделаем все возможное, чтобы вам помочь!',
                            $order->number,
                            (int) $order->price
                        ),
                    ];
                }

                if ($override_delivery->status == DeliveryStatus::ON_POINT_IN) {
                    $track_number = $saved_shipments->first()->delivery->xml_id;
                    $track_string = '';

                    if (!empty($track_number)) {
                        $track_string = sprintf(' ПО НОМЕРУ %s', $track_number);
                    }

                    return [
                        sprintf('ЧАСТЬ ЗАКАЗА %s ПЕРЕДАНА В СЛУЖБУ ДОСТАВКИ%s', $order->number, $track_string),
                        sprintf(
                            'Заказ №%s на сумму %s р. передан в службу доставки.
                        Статус заказа вы можете отслеживать в личном кабинете на сайте: %s',
                            $order->number,
                            $part_price,
                            sprintf('<a href="%s/profile" target="_blank">%s/profile</a>', config('app.showcase_host'), config('app.showcase_host'))
                        ),
                    ];
                }

                if ($override_delivery->status == DeliveryStatus::READY_FOR_RECIPIENT) {
                    $delivery = $order->deliveries->first();

                    if (!empty($delivery) && !empty($delivery->point_id)) {
                        $point = $points->points(
                            $points->newQuery()
                                ->setFilter('id', $delivery->point_id)
                        )->first();

                        $params['Режим работы'] = $point->timetable;
                        $params['Телефон пункта выдачи'] = $point->phone;
                    }

                    $deliveryAddress = $override_delivery->getDeliveryAddressString();
                    $deliveryDate = null;

                    return [
                        sprintf('ЗАКАЗ %s ОЖИДАЕТ В ПУНКТЕ ВЫДАЧИ!', $order->number),
                        sprintf(
                            'Заказ №%s на сумму %d р. ожидает вас в пункте выдачи по адресу: %s в течение семи дней.',
                            $order->number,
                            $part_price,
                            $deliveryAddress
                        ),
                    ];
                }
            }

            if ($override == self::OVERRIDE_SUCCESS) {
                return ['%s, СПАСИБО ЗА ЗАКАЗ', sprintf(
                    'Ваш заказ %s успешно оформлен и принят в обработку',
                    $order->number
                ),
                ];
            }

            if ($override == self::OVERRIDE_CANCEL) {
                return [
                    '%s, ВАШ ЗАКАЗ ОТМЕНЕН',
                    sprintf('Вы отменили ваш заказ %s. Товар вернулся на склад.
                    <br>Пожалуйста, напишите нам, почему вы не смогли забрать заказ.', $order->number),
                ];
            }

            if ($override == self::OVERRIDE_AWAITING_PAYMENT) {
                return [
                    '%s, ВАШ ЗАКАЗ ОЖИДАЕТ ОПЛАТЫ',
                    sprintf('Ваш заказ %s ожидает оплаты. Чтобы перейти
                    <br>к оплате нажмите на кнопку "Оплатить заказ"', $order->number),
                ];
            }

            if ($override == self::OVERRIDE_SUCCESSFUL_PAYMENT) {
                return [
                    sprintf('ЗАКАЗ %s ОПЛАЧЕН И ПРИНЯТ В ОБРАБОТКУ!', $order->number),
                    sprintf(
                        '%s, заказ %s оплачен и принят в обработку.
                    Статус заказа вы можете отслеживать в личном кабинете на сайте: %s',
                        $user->first_name,
                        $order->number,
                        sprintf('<a href="%s/profile" target="_blank">%s/profile</a>', config('app.showcase_host'), config('app.showcase_host'))
                    ),
                ];
            }

            switch ($order->status) {
                case OrderStatus::CREATED:
                case OrderStatus::AWAITING_CONFIRMATION:
                    return ['%s, СПАСИБО ЗА ЗАКАЗ', sprintf(
                        'Ваш заказ %s успешно оформлен и принят в обработку',
                        $order->number
                    ),
                    ];
                case OrderStatus::DELIVERING:
                    $track_number = $saved_shipments->first()->delivery->xml_id;
                    $track_string = '';

                    if (!empty($track_number)) {
                        $track_string = sprintf(' ПО НОМЕРУ %s', $track_number);
                    }

                    return [
                        sprintf('ЗАКАЗ %s ПЕРЕДАН В СЛУЖБУ ДОСТАВКИ%s', $order->number, $track_string),
                        sprintf(
                            'Заказ №%s на сумму %s р. передан в службу доставки.
                        <br>Статус заказа вы можете отслеживать в личном кабинете на сайте: %s',
                            $order->number,
                            (int) $order->price,
                            sprintf('<a href="%s/profile" target="_blank">%s/profile</a>', config('app.showcase_host'), config('app.showcase_host'))
                        ),
                    ];
                case OrderStatus::READY_FOR_RECIPIENT:
                    $delivery = $order->deliveries->first();

                    if (!empty($delivery) && !empty($delivery->point_id)) {
                        $point = $points->points(
                            $points->newQuery()
                                ->setFilter('id', $delivery->point_id)
                        )->first();

                        $params['Режим работы'] = $point->timetable;
                        $params['Телефон пункта выдачи'] = $point->phone;
                    }

                    $deliveryAddress = $delivery ? $delivery->getDeliveryAddressString() : '';
                    $deliveryDate = null;

                    return [
                        sprintf('ЗАКАЗ %s ОЖИДАЕТ В ПУНКТЕ ВЫДАЧИ!', $order->number),
                        sprintf(
                            'Заказ №%s на сумму %d р. ожидает вас в пункте выдачи по адресу: %s в течение семи дней.',
                            $order->number,
                            $order->price,
                            $deliveryAddress
                        ),
                    ];
                    // return ['%s, ВАШ ЗАКАЗ ОЖИДАЕТ ВАС', 'Ваш заказ поступил в пункт самовывоза. Вы можете забрать свою покупку в течении 3-х дней'];
                case OrderStatus::DONE:
                    // $bonus = optional($order->bonuses->first());
                    // $bonusString = '';
                    // if (!empty($bonus->bonus)) {
                    //     $bonusString = sprintf('Вам начислен: %s ₽ бонус. Бонусы будут действительны до %s. Потратить их можно на следующую покупку.<br><br>', $bonus->bonus, $bonus->getExpirationDate() ?? 'не указано');
                    // }
                    $withoutParams = true;
                    return [
                        '%s, ' . sprintf('ВАШ ЗАКАЗ %s ВЫПОЛНЕН', $order->number),
                        'Спасибо что выбрали нас! Надеемся что процесс покупки доставил
                        <br>вам исключительно положительные эмоции.
                        <br><br>Пожалуйста, оставьте свой отзыв о покупках, чтобы помочь нам стать
                        <br>еще лучше и удобнее',
                    ];
                // case OrderStatus::RETURNED:
                //     return [
                //         '%s, ВАШ ЗАКАЗ ОТМЕНЕН',
                //         sprintf('Вы отменили ваш заказ %s. Товар вернулся на склад.
                //         <br>Пожалуйста, напишите нам, почему вы не смогли забрать заказ.', $order->number)
                //     ];
            }
        })();

        if (!$withoutParams) {
            $params['Получатель'] = $receiverFullName;
            $params['Телефон'] = static::formatNumber($receiverPhone);
            $params['Сумма заказа'] = sprintf('%s ₽', (int) $order->price);
            $params['Получение'] = $deliveryMethod;
            if ($deliveryDate !== null) {
                $params['Дата доставки'] = $deliveryDate['normal_length'];
            }
            $params['Адрес доставки'] = $deliveryAddress;
        }

        return [
            'title' => sprintf($title, mb_strtoupper($user->first_name)),
            'text' => $text,
            'button' => $button,
            'params' => $params,
            'shipments' => $shipments->toArray(),
            'hide_shipments_date' => $hideShipmentsDate,
            'delivery_price' => (function () use ($order) {
                $price = (int) $order->delivery_price;

                if ($price == 0) {
                    return 'Бесплатно';
                }

                return $price;
            })(),
            'delivery_method' => empty($deliveryMethod) ? 'Доставка' : $deliveryMethod,
            'total_price' => (int) $order->price,
            'finisher_text' => sprintf(
                'Узнать статус выполнения заказа можно в <a href="%s">Личном кабинете</a>',
                sprintf('%s/profile', config('app.showcase_host'))
            ),
            'ORDER_ID' => $order->number,
            'FULL_NAME' => sprintf('%s %s', $receiverFullNameByParts[0], $receiverFullNameByParts[1] ?? ''),
            'LINK_ACCOUNT' => $redirectService->generateShortUrl(sprintf('%s/profile/orders/%d', config('app.showcase_host'), $order->id)),
            'LINK_PAY' => $link ? $redirectService->generateShortUrl($link) : '',
            'ORDER_DATE' => $order->created_at->toDateString(),
            'ORDER_TIME' => $order->created_at->toTimeString(),
            // 'DELIVERY_TYPE' => optional(DeliveryMethod::methodById(optional($order->deliveries->first())->delivery_method ?? 0))->name ?? '',
            'DELIVERY_TYPE' => (function () use ($order) {
                if (!$order->deliveries->first()) {
                    return '';
                }

                return DeliveryMethod::methodById($order->deliveries->first()->delivery_method)->name;
            })(),
            'DELIVERY_ADDRESS' => (function () use ($order) {
                /** @var Delivery $delivery */
                $delivery = $order->deliveries->first();

                if (!empty($delivery) && !empty($easyDelivery = $delivery->getDeliveryAddressString())) {
                    return $easyDelivery;
                }

                return optional($delivery)
                    ->formDeliveryAddressString($delivery->delivery_address ?? []) ?? '';
            })(),
            'DELIVIRY_ADDRESS' => (function () use ($order) {
                /** @var Delivery $delivery */
                $delivery = $order->deliveries->first();

                if (!empty($delivery) && !empty($easyDelivery = $delivery->getDeliveryAddressString())) {
                    return $easyDelivery;
                }

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

                if ($delivery == null || $delivery->delivery_at == null) {
                    return '';
                }

                if ($delivery->delivery_at->isMidnight()) {
                    return '';
                }

                return $delivery->delivery_at->toTimeString();
            })(),
            'DELIVERY_DATE_TIME' => $deliveryDate['normal_length'] ?? null,
            'DELIVERY_DATE_TIME_SHORT' => $deliveryDate['short'] ?? null,
            'OPER_MODE' => (function () use ($order, $points) {
                $point_id = optional($order->deliveries->first())->point_id;

                if ($point_id == null) {
                    return '';
                }

                return $points->points(
                    $points->newQuery()
                        ->setFilter('id', $point_id)
                )->first()->timetable;
            })(),
            'CALL_TK' => (function () use ($order, $points) {
                $point_id = optional($order->deliveries->first())->point_id;

                if ($point_id == null) {
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
            'NUMBER_BAL' => (int) $order->added_bonus,
            'DEADLINE_BAL' => (function () use ($order) {
                return optional($order
                    ->bonuses()
                    ->orderBy('valid_period')
                    ->first())
                    ->getExpirationDate() ?? 'неопределенного срока';
            })(),
            'AVAILABLE_BAL' => $bonusInfo->available,
            'goods' => $goods->all(),
            'PART_PRICE' => $part_price,
            'TRACK_NUMBER' => $saved_shipments->first() ? $saved_shipments->first()->delivery->xml_id : null,
        ];
    }

    /**
     * Отправить билеты на мастер-классы на почту покупателю заказа и всем участникам.
     * @throws Throwable
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

    public function formatDeliveryDate(Delivery $delivery, bool $shortFormat = false)
    {
        $date = $delivery->delivery_at->locale('ru')->isoFormat($shortFormat ? 'D.MM, dd.' : 'D MMMM, dddd');
        if ($delivery->delivery_time_start && $delivery->delivery_time_end) {
            $date .= sprintf(', с %s до %s', substr($delivery->delivery_time_start, 0, -3), substr($delivery->delivery_time_end, 0, -3));
        }
        return $date;
    }

    private function getFormattedDeliveryDatesByOrder(Order $order, bool $shortFormat = false): ?string
    {
        return $order
            ->deliveries
            ->map(function (Delivery $delivery) use ($shortFormat) {
                return $this->formatDeliveryDate($delivery, $shortFormat);
            })
            ->unique()
            ->join('<br>');
    }

    public function parseName(UserDto $user, Order $order)
    {
        if ($order->receiver_name) {
            $words = explode(' ', $order->receiver_name);
        } else {
            $words = explode(' ', $user->full_name);
        }

        if (isset($words[1])) {
            return $words[1];
        }

        return $words[0];
    }

    public static function formatNumber(string $number)
    {
        $number = substr($number, 1);
        return '+' . substr($number, 0, 1) . ' ' . substr($number, 1, 3) . ' ' . substr($number, 4, 3) . '-' . substr($number, 7, 2) . '-' . substr(
            $number,
            9,
            2
        );
    }

    public static function shortenLink(?string $link)
    {
        if ($link === null) {
            return '';
        }

        /** @var Client $client */
        $client = app(Client::class);

        return $client->request('GET', 'https://clck.ru/--', [
            'query' => [
                'url' => $link,
            ],
        ])->getBody();
    }

    protected function shouldSendPaidNotification(Order $order): bool
    {
        $paid = ($order->payment_status == PaymentStatus::HOLD) || (
            $order->payment_status == PaymentStatus::PAID
            && $order->getOriginal('payment_status') != PaymentStatus::HOLD
            && !$order->is_postpaid
        );

        $created = ($order->status == OrderStatus::CREATED) || ($order->status == OrderStatus::AWAITING_CONFIRMATION);

        if ($order->type == Basket::TYPE_MASTER) {
            $paid = $order->payment_status == PaymentStatus::PAID;
            $created = $order->status == OrderStatus::DONE;
        }

        return $paid && $created;
    }

    protected function getCertificateRequestId(Order $order): ?int
    {
        if (!$order->isCertificateOrder()) {
            return null;
        }

        /** @var BasketItem $basketItem */
        $basketItem = $order->basket->items->first();
        if (!$basketItem || !isset($basketItem->product['request_id'])) {
            return null;
        }

        return (int) $basketItem->product['request_id'];
    }

    /**
     * Связать заказ сертификата с order
     */
    protected function setOrderIdToCertificateRequest(Order $order): void
    {
        $certificateRequestId = $this->getCertificateRequestId($order);

        if (!$certificateRequestId) {
            return;
        }

        resolve(CertificateService::class)->updateRequest($certificateRequestId, new CertificateRequestDto([
            'order_id' => $order->id,
            'order_number' => $order->number,
            'status' => CertificateRequestStatusDto::STATUS_CREATED,
        ]));
    }

    /**
     * Обновить статус заказа сертификата
     */
    protected function setPaymentStatusToCertificateRequest(Order $order): void
    {
        // если платежный статус не изменился, то продолжать смысла нет
        if ($order->payment_status === $order->getOriginal('payment_status')) {
            return;
        }

        $certificateRequestId = $this->getCertificateRequestId($order);

        if (!$certificateRequestId) {
            return;
        }

        if ($order->isPaid()) {
            $certificateService = resolve(CertificateService::class);
            $certificateService->updateRequest($certificateRequestId, new CertificateRequestDto([
                'status' => CertificateRequestStatusDto::STATUS_PAID,
            ]));
        }
    }
}
