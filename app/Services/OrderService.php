<?php

namespace App\Services;

use App\Models\Order\Order;
use App\Models\Order\OrderStatus;
use App\Models\Payment\Payment;
use App\Services\Dto\Internal\OrderTicket;
use App\Services\PaymentService\PaymentService;
use App\Services\PublicEventService\Email\PublicEventCartRepository;
use App\Services\PublicEventService\Email\PublicEventCartStruct;
use Greensight\Message\Dto\Mail\PublicEvent\Ticket\Dto\CustomerInfoDto;
use Greensight\Message\Dto\Mail\PublicEvent\Ticket\Dto\OrderInfoDto;
use Greensight\Message\Dto\Mail\PublicEvent\Ticket\Dto\OrganizerInfoDto;
use Greensight\Message\Dto\Mail\PublicEvent\Ticket\Dto\PublicEventInfoDto;
use Greensight\Message\Dto\Mail\PublicEvent\Ticket\Dto\SpeakerInfoDto;
use Greensight\Message\Dto\Mail\PublicEvent\Ticket\Dto\TicketsInfoDto;
use Greensight\Message\Dto\Mail\PublicEvent\Ticket\TicketEmailDto;
use Greensight\Message\Services\MailService\MailService;
use Illuminate\Support\Collection;
use Pim\Services\PublicEventTicketService\PublicEventTicketService;

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

    /**
     * Вернуть остатки по билетам
     * @param  Collection|Order[]  $orders
     */
    public function returnTickets(Collection $orders): void
    {
        if ($orders->isNotEmpty()) {
            $ticketIds = [];

            foreach ($orders as $order) {
                if ($order->isPublicEventOrder()) {
                    $order->loadMissing('basket.items');
                    foreach ($order->basket->items as $basketItem) {
                        $ticketIds = array_merge($ticketIds, (array)$basketItem->getTicketIds());
                    }
                }
            }

            if ($ticketIds) {
                /** @var PublicEventTicketService $ticketService */
                $ticketService = resolve(PublicEventTicketService::class);
                $ticketService->returnTickets($ticketIds);
            }
        }
    }

    /**
     * @param  Order  $order
     * @param  bool  $loadTickets
     * @return OrderTicket\OrderInfoDto|null
     */
    public function getTicketsInfo(Order $order, bool $loadTickets = false): ?OrderTicket\OrderInfoDto
    {
        if (!$order->isPublicEventOrder()) {
            return null;
        }

        $order->loadMissing('basket.items');
        //Получаем информацию по мастер-классам
        $offerIds = $order->basket->items->pluck('offer_id');
        /** @var PublicEventCartStruct[] $cardStructs */
        [$totalCount, $cardStructs] = (new PublicEventCartRepository())->query()
            ->whereOfferIds($offerIds->all())
            ->pageNumber(1, $offerIds->count())
            ->get();

        //Получаем информацию по билетам
        $tickets = collect();
        if ($loadTickets) {
            /** @var PublicEventTicketService $ticketService */
            $ticketService = resolve(PublicEventTicketService::class);
            $ticketIds = [];
            foreach ($order->basket->items as $item) {
                $ticketIds = array_merge($ticketIds, $item->getTicketIds());
            }
            if ($ticketIds) {
                $ticketQuery = $ticketService->newQuery()->setFilter('id', $ticketIds);
                $tickets = $ticketService->tickets($ticketQuery)->keyBy('id');
            }
        }

        //Формируем данные для отправки e-mail
        $orderInfoDto = new OrderTicket\OrderInfoDto();
        $orderInfoDto->id = $order->id;
        $orderInfoDto->number = $order->number;
        $orderInfoDto->price = $order->price;
        foreach ($cardStructs as $cardStruct) {
            $publicEventInfoDto = new OrderTicket\PublicEventInfoDto();
            $orderInfoDto->addPublicEvent($publicEventInfoDto);

            $organizerInfoDto = new OrderTicket\OrganizerInfoDto();
            $publicEventInfoDto->organizer = $organizerInfoDto;
            $organizerInfoDto->name = $cardStruct->organizer['name'];
            $organizerInfoDto->description = $cardStruct->organizer['description'];
            $organizerInfoDto->phone = $cardStruct->organizer['phone'];
            $organizerInfoDto->email = $cardStruct->organizer['email'];
            $organizerInfoDto->site = $cardStruct->organizer['site'];
            $organizerInfoDto->messengerPhone = $cardStruct->organizer['messenger_phone'];
            foreach ($order->basket->items as $item) {
                if ($cardStruct->sprintId == $item->getSprintId()) {
                    $ticketsInfoDto = new OrderTicket\TicketsInfoDto();
                    $publicEventInfoDto->addTicketInfo($ticketsInfoDto);
                    $ticketsInfoDto->name = $item->name;
                    $ticketsInfoDto->ticketTypeName = $item->getTicketTypeName();
                    $ticketsInfoDto->photoId = $cardStruct->image;
                    $ticketsInfoDto->nearestDate = $cardStruct->nearestDate;
                    $ticketsInfoDto->nearestTimeFrom = $cardStruct->nearestTimeFrom;
                    $ticketsInfoDto->nearestPlaceName = $cardStruct->nearestPlaceName;
                    $ticketsInfoDto->ticketsQty = count($item->getTicketIds());
                    $ticketsInfoDto->price = (float)$item->price;
                    $ticketsInfoDto->pricePerOne = $ticketsInfoDto->price/$ticketsInfoDto->ticketsQty;

                    foreach ($cardStruct->speakers as $speaker) {
                        $speakerInfoDto = new OrderTicket\SpeakerInfoDto();
                        $ticketsInfoDto->addSpeaker($speakerInfoDto);
                        $speakerInfoDto->firstName = $speaker['first_name'];
                        $speakerInfoDto->middleName = $speaker['middle_name'];
                        $speakerInfoDto->lastName = $speaker['last_name'];
                        $speakerInfoDto->profession = $speaker['profession'];
                    }

                    if ($loadTickets) {
                        foreach ($item->getTicketIds() as $ticketId) {
                            if ($tickets->has($ticketId)) {
                                $ticket = $tickets[$ticketId];
                                $ticketDto = new OrderTicket\TicketDto();
                                $ticketsInfoDto->addTicket($ticketDto);
                                $ticketDto->firstName = $ticket->first_name;
                                $ticketDto->middleName = $ticket->middle_name;
                                $ticketDto->lastName = $ticket->last_name;
                                $ticketDto->phone = $ticket->phone;
                                $ticketDto->email = $ticket->email;
                            }
                        }
                    }

                    break;
                }
            }
        }

        return $orderInfoDto;
    }

    /**
     * Отправить билеты на мастер-классы на почту покупателю заказа
     * @param  Order  $order
     * @throws \Exception
     */
    public function sendTicketsEmail(Order $order): void
    {
        if (!$order->isPublicEventOrder()) {
            return;
        }

        if (!$order->receiver_email) {
            throw new \Exception('Не указан email-адрес получателя');
        }

        $internalOrderInfoDto = $this->getTicketsInfo($order);
        //Формируем данные для отправки e-mail
        $orderInfoDto = new OrderInfoDto();
        $orderInfoDto->setId($internalOrderInfoDto->id);
        $orderInfoDto->setNumber($internalOrderInfoDto->number);
        $orderInfoDto->setPrice($internalOrderInfoDto->price);
        foreach ($internalOrderInfoDto->publicEvents as $publicEvent) {
            $publicEventInfoDto = new PublicEventInfoDto();
            $orderInfoDto->addPublicEvent($publicEventInfoDto);

            $organizerInfoDto = new OrganizerInfoDto();
            $publicEventInfoDto->setOrganizer($organizerInfoDto);
            $organizer = $publicEvent->organizer;
            $organizerInfoDto->setName($organizer->name);
            $organizerInfoDto->setDescription($organizer->description);
            $organizerInfoDto->setPhone($organizer->phone);
            $organizerInfoDto->setEmail($organizer->email);
            $organizerInfoDto->setSite($organizer->site);
            $organizerInfoDto->setMessengerPhone($organizer->messengerPhone);
            foreach ($publicEvent->ticketsInfo as $ticket) {
                $ticketsInfoDto = new TicketsInfoDto();
                $publicEventInfoDto->addTicket($ticketsInfoDto);
                $ticketsInfoDto->setName($ticket->name);
                $ticketsInfoDto->setTicketTypeName($ticket->ticketTypeName);
                $ticketsInfoDto->setPhotoId($ticket->photoId);
                $ticketsInfoDto->setNearestDate($ticket->nearestDate);
                $ticketsInfoDto->setNearestTimeFrom($ticket->nearestTimeFrom);
                $ticketsInfoDto->setNearestPlaceName($ticket->nearestPlaceName);
                $ticketsInfoDto->setPrice($ticket->price);
                $ticketsInfoDto->setTicketsQty($ticket->ticketsQty);

                foreach ($ticket->speakers as $speaker) {
                    $speakerInfoDto = new SpeakerInfoDto();
                    $ticketsInfoDto->addSpeaker($speakerInfoDto);
                    $speakerInfoDto->setFirstName($speaker->firstName);
                    $speakerInfoDto->setMiddleName($speaker->middleName);
                    $speakerInfoDto->setLastName($speaker->lastName);
                    $speakerInfoDto->setProfession($speaker->profession);
                }
            }
        }

        $ticketEmailDto = new TicketEmailDto();
        $customerInfoDto = new CustomerInfoDto();
        $customerInfoDto->setName($order->receiver_name);
        $customerInfoDto->setPhone($order->receiver_phone);
        $customerInfoDto->setEmail($order->receiver_email);
        $ticketEmailDto->setCustomer($customerInfoDto);
        $ticketEmailDto->setOrder($orderInfoDto);
        $ticketEmailDto->addTo($order->receiver_email, $order->receiver_name);
        /** @var MailService $mailService */
        $mailService = resolve(MailService::class);
        $mailService->send($ticketEmailDto);
    }
}
