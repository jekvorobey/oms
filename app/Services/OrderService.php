<?php

namespace App\Services;

use App\Models\Order\Order;
use App\Models\Order\OrderStatus;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentStatus;
use App\Services\Dto\In\OrderReturn\OrderReturnDtoBuilder;
use App\Services\Dto\Internal\PublicEventOrder;
use App\Services\PaymentService\PaymentService;
use App\Services\PublicEventService\Email\PublicEventCartRepository;
use App\Services\PublicEventService\Email\PublicEventCartStruct;
use Illuminate\Support\Collection;
use Pim\Services\PublicEventTicketService\PublicEventTicketService;
use App\Observers\Order\OrderObserver;

/**
 * Класс-бизнес логики по работе с заказами (без чекаута и доставки)
 * Class OrderService
 * @package App\Services
 */
class OrderService
{
    /**
     * Получить объект заказа по его id
     */
    public function getOrder(int $orderId): ?Order
    {
        return Order::find($orderId);
    }

    /**
     * Вручную оплатить заказ.
     * Примечание: оплата по заказам автоматически должна поступать от платежной системы!
     * @throws \Exception
     */
    public function pay(Order $order): bool
    {
        /** @var Payment $payment */
        $payment = $order->payments->first();
        if (!$payment) {
            throw new \Exception('Оплата для заказа не найдена');
        }
        /** @var PaymentService $paymentService */
        $paymentService = resolve(PaymentService::class);

        return $paymentService->pay($payment);
    }

    /**
     * Обновить статус оплаты заказа в соотвествии со статусами оплат
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

        $this->setPaymentStatus($order, $payment->status);
    }

    /**
     * Отменить заказ
     * @throws \Exception
     */
    public function cancel(Order $order, ?int $orderReturnReasonId = null): bool
    {
        if ($order->status >= OrderStatus::DONE) {
            throw new \Exception('Заказ, начиная со статуса "Выполнен", нельзя отменить');
        }

        $order->is_canceled = true;
        $order->return_reason_id ??= $orderReturnReasonId;

        if (!$order->save()) {
            return false;
        }

        if ($order->isCertificateOrder()) {
            $orderReturnDto = (new OrderReturnDtoBuilder())->buildFromOrderAllCertificates($order);
        } else {
            $orderReturnDto = (new OrderReturnDtoBuilder())->buildFromOrder($order);
        }

        /** @var OrderReturnService $orderReturnService */
        $orderReturnService = resolve(OrderReturnService::class);
        $orderReturnService->create($orderReturnDto);

        if ($order->payment_status === PaymentStatus::HOLD) {
            /** @var Payment $payment */
            $payment = $order->payments->last();
            $paymentSystem = $payment->paymentSystem();
            if ($paymentSystem) {
                $paymentSystem->cancel($payment->external_payment_id);
            }
        }

        return true;
    }

    /**
     * Вернуть деньги при деактивации сертификата
     * @TODO переделать на передачу $certificateId вместо $sum
     */
    public function refundByCertificate(Order $order, int $sum): bool
    {
        $orderReturnDto = (new OrderReturnDtoBuilder())->buildFromOrderEachCertificate($order, $sum);

        try {
            /** @var OrderReturnService $orderReturnService */
            $orderReturnService = resolve(OrderReturnService::class);
            $orderReturn = $orderReturnService->create($orderReturnDto);
        } catch (\Throwable $e) {
            report($e);
            return false;
        }

        return (bool) $orderReturn;
    }

    /**
     * Установить статус оплаты заказа
     */
    protected function setPaymentStatus(Order $order, int $status, bool $save = true): bool
    {
        $order->payment_status = $status;
        if ($order->payment_status == PaymentStatus::TIMEOUT) {
            $order->is_canceled = true;
        }

        return $save ? $order->save() : true;
    }

    /**
     * Пометить заказ как проблемный
     */
    public function markAsProblem(Order $order): bool
    {
        $order->is_problem = true;

        return $order->save();
    }

    /**
     * Пометить заказ как непроблемный, если все его отправления непроблемные
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
     * Вернуть остатки по билетам
     * @param Collection|Order[] $orders
     * @param bool $saveOrders - сохранить данные по заказам
     */
    public function returnTickets(Collection $orders, bool $saveOrders = true): void
    {
        if ($orders->isNotEmpty()) {
            $ticketIds = [];

            foreach ($orders as $order) {
                if ($order->isPublicEventOrder()) {
                    $order->loadMissing('basket.items');
                    foreach ($order->basket->items as $basketItem) {
                        $ticketIds = array_merge($ticketIds, (array) $basketItem->getTicketIds());
                    }
                }
            }

            if ($ticketIds) {
                /** @var PublicEventTicketService $ticketService */
                $ticketService = resolve(PublicEventTicketService::class);
                $ticketService->returnTickets($ticketIds);
            }

            foreach ($orders as $order) {
                if ($order->isPaid()) {
                    $order->status = OrderStatus::RETURNED;
                    if ($saveOrders) {
                        $order->save();
                    }
                }
            }
        }
    }

    /**
     * @throws \Pim\Core\PimException
     */
    public function getPublicEventsOrderInfo(
        Order $order,
        bool $loadTickets = false,
        ?int $basketItemId = null
    ): ?PublicEventOrder\OrderInfoDto {
        if (!$order->isPublicEventOrder()) {
            return null;
        }

        $order->loadMissing('basket.items');
        $basketItems = $basketItemId ? $order->basket->items->where('id', $basketItemId) : $order->basket->items;
        //Получаем информацию по мастер-классам
        $offerIds = $basketItems->pluck('offer_id');
        /** @var PublicEventCartStruct[] $cardStructs */
        [, $cardStructs] = (new PublicEventCartRepository())->query()
            ->whereOfferIds($offerIds->all())
            ->pageNumber(1, $offerIds->count())
            ->get();

        //Получаем информацию по билетам
        $tickets = collect();
        if ($loadTickets) {
            /** @var PublicEventTicketService $ticketService */
            $ticketService = resolve(PublicEventTicketService::class);
            $ticketIds = [];
            foreach ($basketItems as $item) {
                $ticketIds = array_merge($ticketIds, $item->getTicketIds());
            }
            if ($ticketIds) {
                $ticketQuery = $ticketService->newQuery()->setFilter('id', $ticketIds);
                $tickets = $ticketService->tickets($ticketQuery)->keyBy('id');
            }
        }

        //Формируем данные для отправки e-mail
        $orderInfoDto = new PublicEventOrder\OrderInfoDto();
        $orderInfoDto->id = $order->id;
        $orderInfoDto->type = $order->type;
        $orderInfoDto->number = $order->number;
        $orderInfoDto->createdAt = $order->created_at;
        $orderInfoDto->price = $order->price;
        $orderInfoDto->status = $order->status;
        $orderInfoDto->paymentStatus = $order->payment_status;
        $orderInfoDto->isCanceled = $order->is_canceled;
        $orderInfoDto->receiverName = $order->receiver_name;
        $orderInfoDto->receiverEmail = $order->receiver_email;
        $orderInfoDto->receiverPhone = $order->receiver_phone;
        foreach ($cardStructs as $cardStruct) {
            $publicEventInfoDto = new PublicEventOrder\PublicEventInfoDto();
            $orderInfoDto->addPublicEvent($publicEventInfoDto);
            $publicEventInfoDto->id = $cardStruct->id;
            $publicEventInfoDto->code = $cardStruct->code;
            $publicEventInfoDto->dateFrom = $cardStruct->dateFrom;
            $publicEventInfoDto->dateTo = $cardStruct->dateTo;

            foreach ($cardStruct->speakers as $speaker) {
                $speakerInfoDto = new PublicEventOrder\SpeakerInfoDto();
                $speakerInfoDto->id = $speaker['id'];
                $speakerInfoDto->firstName = $speaker['first_name'];
                $speakerInfoDto->middleName = $speaker['middle_name'];
                $speakerInfoDto->lastName = $speaker['last_name'];
                $speakerInfoDto->phone = $speaker['phone'];
                $speakerInfoDto->email = $speaker['email'];
                $speakerInfoDto->lastName = $speaker['last_name'];
                $speakerInfoDto->profession = $speaker['profession'];
                $speakerInfoDto->avatar = $speaker['avatar'];
                $speakerInfoDto->setInstagram($speaker['instagram']);
                $speakerInfoDto->setFacebook($speaker['facebook']);
                $speakerInfoDto->setLinkedin($speaker['linkedin']);
                $publicEventInfoDto->addSpeaker($speakerInfoDto);
            }

            foreach ($cardStruct->places as $place) {
                $placeInfoDto = new PublicEventOrder\PlaceInfoDto();
                $placeInfoDto->id = $place['id'];
                $placeInfoDto->name = $place['name'];
                $placeInfoDto->description = $place['description'];
                $placeInfoDto->cityId = $place['city_id'];
                $placeInfoDto->cityName = $place['city_name'];
                $placeInfoDto->address = $place['address'];
                $placeInfoDto->latitude = $place['latitude'];
                $placeInfoDto->longitude = $place['longitude'];
                $placeInfoDto->longitude = $place['longitude'];
                foreach ($place['gallery'] as $gallery) {
                    $galleryItemInfoDto = new PublicEventOrder\GalleryItemInfoDto();
                    $placeInfoDto->addGalleryItem($galleryItemInfoDto);
                    $galleryItemInfoDto->fileId = $gallery['value'];
                    $galleryItemInfoDto->collection = $gallery['collection'];
                    $galleryItemInfoDto->type = $gallery['type'];
                }
                $publicEventInfoDto->addPlace($placeInfoDto);
            }

            foreach ($cardStruct->stages as $stage) {
                $stageInfoDto = new PublicEventOrder\StageInfoDto();
                $stageInfoDto->id = $stage['id'];
                $stageInfoDto->name = $stage['name'];
                $stageInfoDto->description = $stage['description'];
                $stageInfoDto->result = $stage['result'];
                $stageInfoDto->raider = $stage['raider'];
                $stageInfoDto->setDate($stage['date']);
                $stageInfoDto->setTimeFrom($stage['time_from']);
                $stageInfoDto->setTimeTo($stage['time_to']);
                $stageInfoDto->placeId = $stage['place_id'];
                $stageInfoDto->speakerIds = $stage['speaker_ids'];
                $publicEventInfoDto->addStage($stageInfoDto);
            }

            $organizerInfoDto = new PublicEventOrder\OrganizerInfoDto();
            $publicEventInfoDto->organizer = $organizerInfoDto;
            $organizerInfoDto->name = $cardStruct->organizer['name'];
            $organizerInfoDto->description = $cardStruct->organizer['description'];
            $organizerInfoDto->phone = $cardStruct->organizer['phone'];
            $organizerInfoDto->email = $cardStruct->organizer['email'];
            $organizerInfoDto->site = $cardStruct->organizer['site'];
            $organizerInfoDto->messengerPhone = $cardStruct->organizer['messenger_phone'] ?: $cardStruct->organizer['phone'];
            foreach ($basketItems as $item) {
                if ($cardStruct->sprintId == $item->getSprintId()) {
                    $ticketsInfoDto = new PublicEventOrder\TicketsInfoDto();
                    $publicEventInfoDto->addTicketInfo($ticketsInfoDto);
                    $ticketsInfoDto->id = $item->id;
                    $ticketsInfoDto->code = $item->code;
                    $ticketsInfoDto->name = $item->name;
                    $ticketsInfoDto->ticketTypeName = $item->getTicketTypeName();
                    $ticketsInfoDto->photoId = $cardStruct->image;
                    $ticketsInfoDto->nearestDate = $cardStruct->nearestDate;
                    $ticketsInfoDto->nearestTimeFrom = $cardStruct->nearestTimeFrom;
                    $ticketsInfoDto->nearestPlaceName = $cardStruct->nearestPlaceName;
                    $ticketsInfoDto->ticketsQty = count($item->getTicketIds());
                    $ticketsInfoDto->price = (float) $item->price;
                    $ticketsInfoDto->pricePerOne = $ticketsInfoDto->price / $ticketsInfoDto->ticketsQty;

                    if ($loadTickets) {
                        foreach ($item->getTicketIds() as $ticketId) {
                            if ($tickets->has($ticketId)) {
                                $ticket = $tickets[$ticketId];
                                $ticketDto = new PublicEventOrder\TicketInfoDto();
                                $ticketsInfoDto->addTicket($ticketDto);
                                $ticketDto->id = $ticket->id;
                                $ticketDto->code = $ticket->code;
                                $ticketDto->firstName = $ticket->first_name;
                                $ticketDto->middleName = $ticket->middle_name;
                                $ticketDto->lastName = $ticket->last_name;
                                $ticketDto->phone = OrderObserver::formatNumber($ticket->phone);
                                $ticketDto->email = $ticket->email;
                            }
                        }
                    }
                }
            }
        }

        return $orderInfoDto;
    }

    /**
     * Отправить билеты на мастер-классы на почту покупателю заказа
     * @throws \Throwable
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter
     */
    public function sendTicketsEmail(Order $order): void
    {
        // if (!$order->isPublicEventOrder()) {
        //     return;
        // }

        // if (!$order->receiver_email) {
        //     throw new \Exception('Не указан email-адрес получателя');
        // }

        // $internalOrderInfoDto = $this->getPublicEventsOrderInfo($order);
        // //Формируем данные для отправки e-mail
        // $orderInfoDto = new OrderInfoDto();
        // $orderInfoDto->setId($internalOrderInfoDto->id);
        // $orderInfoDto->setNumber($internalOrderInfoDto->number);
        // $orderInfoDto->setPrice($internalOrderInfoDto->price);
        // foreach ($internalOrderInfoDto->publicEvents as $publicEvent) {
        //     $publicEventInfoDto = new PublicEventInfoDto();
        //     $orderInfoDto->addPublicEvent($publicEventInfoDto);

        //     foreach ($publicEvent->speakers as $speaker) {
        //         $speakerInfoDto = new SpeakerInfoDto();
        //         $publicEventInfoDto->addSpeaker($speakerInfoDto);
        //         $speakerInfoDto->setFirstName($speaker->firstName);
        //         $speakerInfoDto->setMiddleName($speaker->middleName);
        //         $speakerInfoDto->setLastName($speaker->lastName);
        //         $speakerInfoDto->setProfession($speaker->profession);
        //     }

        //     $organizerInfoDto = new OrganizerInfoDto();
        //     $publicEventInfoDto->setOrganizer($organizerInfoDto);
        //     $organizer = $publicEvent->organizer;
        //     $organizerInfoDto->setName($organizer->name);
        //     $organizerInfoDto->setDescription($organizer->description);
        //     $organizerInfoDto->setPhone($organizer->phone);
        //     $organizerInfoDto->setEmail($organizer->email);
        //     $organizerInfoDto->setSite($organizer->site);
        //     $organizerInfoDto->setMessengerPhone($organizer->messengerPhone);
        //     foreach ($publicEvent->ticketsInfo as $ticket) {
        //         $ticketsInfoDto = new TicketsInfoDto();
        //         $publicEventInfoDto->addTicket($ticketsInfoDto);
        //         $ticketsInfoDto->setName($ticket->name);
        //         $ticketsInfoDto->setTicketTypeName($ticket->ticketTypeName);
        //         $ticketsInfoDto->setPhotoId($ticket->photoId);
        //         $ticketsInfoDto->setNearestDate($ticket->nearestDate);
        //         $ticketsInfoDto->setNearestTimeFrom($ticket->nearestTimeFrom);
        //         $ticketsInfoDto->setNearestPlaceName($ticket->nearestPlaceName);
        //         $ticketsInfoDto->setPrice($ticket->price);
        //         $ticketsInfoDto->setTicketsQty($ticket->ticketsQty);
        //     }
        // }

        // $ticketEmailDto = new TicketEmailDto();
        // $customerInfoDto = new CustomerInfoDto();
        // $customerInfoDto->setName($order->receiver_name);
        // $customerInfoDto->setPhone($order->receiver_phone);
        // $customerInfoDto->setEmail($order->receiver_email);
        // $ticketEmailDto->setCustomer($customerInfoDto);
        // $ticketEmailDto->setOrder($orderInfoDto);
        // $ticketEmailDto->addTo($order->receiver_email, $order->receiver_name);

        // $documentService = resolve(DocumentService::class);
        // $documentDto = $documentService->getOrderPdfTickets($order);
        // if ($documentDto->success) {
        //     $ticketEmailDto->addFileId($documentDto->file_id);
        // }

        // $mailService = resolve(MailService::class);
        // $mailService->send($ticketEmailDto);
    }
}
