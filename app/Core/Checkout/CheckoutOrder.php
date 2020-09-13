<?php

namespace App\Core\Checkout;

use App\Core\Order\OrderWriter;
use App\Models\Basket\Basket;
use App\Models\Basket\BasketItem;
use App\Models\Delivery\Delivery;
use App\Models\Delivery\Shipment;
use App\Models\Delivery\ShipmentItem;
use App\Models\Order\Order;
use App\Models\Order\OrderBonus;
use App\Models\Order\OrderDiscount;
use App\Models\Order\OrderPromoCode;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentSystem;
use Carbon\Carbon;
use Exception;
use Greensight\CommonMsa\Services\FileService\FileService;
use Greensight\Customer\Dto\CustomerBonusDto;
use Greensight\Customer\Services\CustomerService\CustomerService;
use Greensight\Message\Services\ServiceNotificationService\ServiceNotificationService;
use Greensight\Store\Dto\StockDto;
use Greensight\Store\Services\StockService\StockService;
use GuzzleHttp\Client;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Pim\Dto\PublicEvent\MediaDto;
use Pim\Dto\PublicEvent\PlaceDto;
use Pim\Dto\PublicEvent\PublicEventTicketTypeDto;
use Pim\Dto\PublicEvent\TicketDto;
use Pim\Dto\PublicEvent\TicketStatus;
use Pim\Services\PublicEventMediaService\PublicEventMediaService;
use Pim\Services\PublicEventOrganizerService\PublicEventOrganizerService;
use Pim\Services\PublicEventPlaceService\PublicEventPlaceService;
use Pim\Services\PublicEventService\PublicEventService;
use Pim\Services\PublicEventSpeakerService\PublicEventSpeakerService;
use Pim\Services\PublicEventSprintService\PublicEventSprintService;
use Pim\Services\PublicEventSprintStageService\PublicEventSprintStageService;
use Pim\Services\PublicEventTicketService\PublicEventTicketService;
use Pim\Services\PublicEventTicketTypeService\RestPublicEventTicketTypeService;
use Spatie\CalendarLinks\Link;

class CheckoutOrder
{
    // primary data
    /** @var int */
    public $customerId;
    /** @var int */
    public $basketId;

    /** @var string */
    public $receiverName;
    /** @var string */
    public $receiverPhone;
    /** @var string */
    public $receiverEmail;

    // marketing data
    /** @var float */
    public $cost;
    /** @var float */
    public $price;
    /** @var int */
    public $paymentMethodId;
    /** @var int */
    public $confirmationTypeId;
    /** @var int */
    public $spentBonus;
    /** @var int */
    public $addedBonus;
    /** @var string[] */
    public $certificates;
    /** @var CheckoutItemPrice[] */
    public $prices;
    /** @var OrderPromoCode[] */
    public $promoCodes;
    /** @var OrderDiscount[] */
    public $discounts;
    /** @var OrderBonus[] */
    public $bonuses;

    // delivery data
    /** @var int */
    public $deliveryTypeId;
    /** @var int */
    public $deliveryCost;
    /** @var int */
    public $deliveryPrice;


    /** @var CheckoutDelivery[] */
    public $deliveries;

    /** @var Collection|CheckoutPublicEvent[] */
    public $publicEvents;

    public static function fromArray(array $data): self
    {
        $order = new self();
        @([
            'customerId' => $order->customerId,
            'basketId' => $order->basketId,
            'receiverName' => $order->receiverName,
            'receiverPhone' => $order->receiverPhone,
            'receiverEmail' => $order->receiverEmail,
            'cost' => $order->cost,
            'price' => $order->price,
            'paymentMethodId' => $order->paymentMethodId,
            'confirmationTypeId' => $order->confirmationTypeId,
            'spentBonus' => $order->spentBonus,
            'addedBonus' => $order->addedBonus,
            'promoCodes' => $promoCodes,
            'certificates' => $order->certificates,
            'prices' => $prices,
            'discounts' => $discounts,
            'bonuses' => $bonuses,

            'deliveryTypeId' => $order->deliveryTypeId,
            'deliveryPrice' => $order->deliveryPrice,
            'deliveryCost' => $order->deliveryCost,
            'deliveries' => $deliveries,

            'publicEvents' => $publicEvents,
        ] = $data);

        foreach ($prices as $priceData) {
            $checkoutItemPrice = CheckoutItemPrice::fromArray($priceData);
            $order->prices[$checkoutItemPrice->basketItemId] = $checkoutItemPrice;
        }

        foreach ($deliveries as $deliveryData) {
            $order->deliveries[] = CheckoutDelivery::fromArray($deliveryData);
        }

        $order->publicEvents = collect();
        foreach ($publicEvents as $publicEvent) {
            $order->publicEvents->push(CheckoutPublicEvent::fromArray($publicEvent));
        }

        $order->promoCodes = [];
        foreach ($promoCodes as $promoCode) {
            $order->promoCodes[] = new OrderPromoCode($promoCode);
        }

        $order->discounts = [];
        foreach ($discounts as $discount) {
            $order->discounts[] = new OrderDiscount($discount);
        }

        $order->bonuses = [];
        foreach ($bonuses as $bonus) {
            $order->bonuses[] = new OrderBonus($bonus);
        }

        return $order;
    }

    /**
     * @throws Exception
     * @return array
     */
    public function save(): array
    {
        return DB::transaction(function () {
            $this->checkOffersStocks();
            $this->commitPrices();
            $order = $this->createOrder();
            $this->debitingBonus($order);
            $this->createShipments($order);
            $this->createTickets($order);
            $this->createPayment($order);
            $this->createOrderDiscounts($order);
            $this->createOrderPromoCodes($order);
            $this->createOrderBonuses($order);

            return [$order->id, $order->number];
        });
    }

    /**
     * Проверить, что кол-во товаров в корзине еще доступно к покупке
     * @throws Exception
     */
    private function checkOffersStocks(): void
    {
        $basket = $this->basket();
        if (!$basket->isProductBasket() && !$basket->isPublicEventBasket()) {
            return;
        }

        $offerIds = [];
        $storeIds = [];
        $ticketTypeIds = [];
        foreach ($basket->items as $item) {
            $offerIds[] = $item->offer_id;
            if ($basket->isProductBasket()) {
                if (!in_array($item->getStoreId(), $storeIds)) {
                    $storeIds[] = $item->getStoreId();
                }
            } elseif ($basket->isPublicEventBasket()) {
                $ticketTypeIds[] = $item->getTicketTypeId();
            }
        }

        $stocks = collect();
        $ticketTypes = collect();
        if ($basket->isProductBasket()) {
            if ($offerIds && $storeIds) {
                /** @var StockService $stockService */
                $stockService = resolve(StockService::class);
                $stocksQuery = $stockService->newQuery()
                    ->setFilter('offer_id', $offerIds)
                    ->setFilter('store_id', $storeIds);
                $stocks = $stockService->stocks($stocksQuery);
            }
        } elseif ($basket->isPublicEventBasket()) {
            if ($ticketTypeIds) {
                /** @var RestPublicEventTicketTypeService $ticketTypeService */
                $ticketTypeService = resolve(RestPublicEventTicketTypeService::class);
                $ticketTypes = $ticketTypeService->query()
                    ->setFilter('id', $ticketTypeIds)
                    ->addFields('tickettype', 'id', 'qty', 'placesOccupied')
                    ->get();
            }
        }

        foreach ($basket->items as $item) {
            if ($basket->isProductBasket()) {
                /** @var StockDto $stock */
                $stock = $stocks
                    ->where('store_id', $item->getStoreId())
                    ->where('offer_id', $item->offer_id)
                    ->first();
                if (!$stock || $item->qty > $stock->qty) {
                    throw new Exception("Qty product offer by id {$item->offer_id} more than available at store with id {$item->getStoreId()}", 400);
                }
            } elseif ($basket->isPublicEventBasket()) {
                /** @var PublicEventTicketTypeDto $ticketType */
                $ticketType = $ticketTypes->where('id', $item->getTicketTypeId())->first();
                if (!$ticketType) {
                    throw new Exception("TicketType by id={$item->getTicketTypeId()} not found", 404);
                }
                $qtyFree = $ticketType->qty - $ticketType->placesOccupied;
                if ($item->qty > $qtyFree) {
                    throw new Exception("Qty public event offer by id {$item->offer_id} more than available", 400);
                }
            }
        }
    }

    /**
     * @throws Exception
     */
    private function commitPrices(): void
    {
        $basket = $this->basket();
        foreach ($basket->items as $item) {
            $priceItem = $this->prices[$item->id] ?? null;
            if (!$priceItem) {
                throw new Exception('price is not supplied for basket item');
            }
            $item->cost = $priceItem->cost;
            $item->price = $priceItem->price;
            $item->bonus_spent = $priceItem->bonusSpent ?? 0;
            $item->bonus_discount = $priceItem->bonusDiscount ?? 0;
            $item->save();
        }
    }

    private function createOrder(): Order
    {
        $order = new Order();
        $order->customer_id = $this->customerId;
        $order->number = Order::makeNumber();
        $order->basket_id = $this->basketId;
        $order->type = $this->basket()->type;
        $order->receiver_name = $this->receiverName;
        $order->receiver_email = $this->receiverEmail;
        $order->receiver_phone = $this->receiverPhone;
        $order->confirmation_type = $this->confirmationTypeId;
        $order->cost = $this->cost;
        $order->price = $this->price;
        $order->spent_bonus = $this->spentBonus;
        $order->added_bonus = $this->addedBonus;
        $order->certificates = $this->certificates;

        $order->delivery_type = $this->deliveryTypeId;
        $order->delivery_cost = $this->deliveryCost;
        $order->delivery_price = $this->deliveryPrice;

        $order->save();
        return $order;
    }

    /**
     * @param Order $order
     */
    private function debitingBonus(Order $order)
    {
        $totalBonusSpent = 0;
        $basket = $this->basket();
        foreach ($basket->items as $item) {
            $totalBonusSpent += $item->bonus_spent;
        }

        if ($totalBonusSpent > 0) {
            $customerService = resolve(CustomerService::class);
            $customerService->debitingBonus($this->customerId, $order->id, (string)$order->id, $totalBonusSpent);
        }
    }

    /**
     * @param Order $order
     */
    private function createOrderDiscounts(Order $order)
    {
        /** @var OrderDiscount $discount */
        foreach ($this->discounts as $discount) {
            $discount->order_id = $order->id;
            $discount->save();
        }
    }

    /**
     * @param Order $order
     */
    private function createOrderPromoCodes(Order $order)
    {
        /** @var OrderPromoCode $promoCode */
        foreach ($this->promoCodes as $promoCode) {
            $promoCode->order_id = $order->id;
            $promoCode->save();
        }
    }

    /**
     * @param Order $order
     */
    private function createOrderBonuses(Order $order)
    {
        /** @var CustomerService $customerService */
        $customerService = resolve(CustomerService::class);

        /** @var OrderBonus $bonus */
        foreach ($this->bonuses as $bonus) {
            $customerBonus = new CustomerBonusDto();
            $customerBonus->customer_id = $this->customerId;
            $customerBonus->name = (string) $order->id;
            $customerBonus->value = $bonus->bonus;
            $customerBonus->status = CustomerBonusDto::STATUS_ON_HOLD;
            $customerBonus->type = CustomerBonusDto::TYPE_ORDER;
            $customerBonus->expiration_date = null; // Без ограничений для статуса STATUS_ON_HOLD
            $customerBonus->order_id = $order->id;
            $customerBonusId = $customerService->createBonus($customerBonus);

            $bonus->status = OrderBonus::STATUS_ON_HOLD;
            $bonus->customer_bonus_id = $customerBonusId;
            $bonus->order_id = $order->id;
            $bonus->save();
        }
    }

    /**
     * @param Order $order
     * @throws Exception
     */
    private function createShipments(Order $order): void
    {
        if (!$this->deliveries) {
            return;
        }

        $offerToBasketMap = $this->offerToBasketMap();

        $shipmentNumber = 1;
        foreach ($this->deliveries as $i => $checkoutDelivery) {
            $delivery = new Delivery();
            $delivery->order_id = $order->id;
            $delivery->number = Delivery::makeNumber($order->number, ++$i);

            $delivery->delivery_method = $checkoutDelivery->deliveryMethod;
            $delivery->delivery_service = $checkoutDelivery->deliveryService;
            $delivery->tariff_id = $checkoutDelivery->tariffId;
            $delivery->cost = $checkoutDelivery->cost;

            $delivery->receiver_name = $checkoutDelivery->receiverName;
            $delivery->receiver_email = $checkoutDelivery->receiverEmail;
            $delivery->receiver_phone = $checkoutDelivery->receiverPhone;
            $delivery->delivery_address = $checkoutDelivery->deliveryAddress;

            $delivery->point_id = $checkoutDelivery->pointId;
            $delivery->delivery_at = $checkoutDelivery->selectedDate;
            $delivery->delivery_time_start = $checkoutDelivery->deliveryTimeStart;
            $delivery->delivery_time_end = $checkoutDelivery->deliveryTimeEnd;
            $delivery->delivery_time_code = $checkoutDelivery->deliveryTimeCode;
            $delivery->dt = $checkoutDelivery->dt;
            $delivery->pdd = $checkoutDelivery->pdd;

            $delivery->save();

            foreach ($checkoutDelivery->shipments as $checkoutShipment) {
                $shipment = new Shipment();
                $shipment->delivery_id = $delivery->id;
                $shipment->merchant_id = $checkoutShipment->merchantId;
                $shipment->psd = $checkoutShipment->psd;
                $shipment->required_shipping_at = $checkoutShipment->psd;
                $shipment->store_id = $checkoutShipment->storeId;
                $shipment->number = Shipment::makeNumber($order->number, $i, $shipmentNumber++);
                $shipment->save();

                foreach ($checkoutShipment->items as [$offerId, $bundleId]) {
                    $key = $bundleId ?
                        $offerId . ':' . $bundleId :
                        $offerId;
                    $basketItemId = $offerToBasketMap[$key] ?? null;
                    if (!$basketItemId) {
                        throw new Exception('shipment has which not in basket');
                    }
                    $shipmentItem = new ShipmentItem();
                    $shipmentItem->shipment_id = $shipment->id;
                    $shipmentItem->basket_item_id = $basketItemId;

                    $shipmentItem->save();
                }
            }
        }
    }

    /**
     * @throws Exception
     */
    private function createTickets(Order $order): void
    {
        if ($this->publicEvents->isEmpty()) {
            return;
        }

        /** @var PublicEventTicketService $ticketService */
        $ticketService = resolve(PublicEventTicketService::class);

        /** @var Collection|BasketItem[] $basketItems */
        $basketItems = BasketItem::query()->where('basket_id', $this->basketId)->get();
        /** @var Collection|CheckoutPublicEvent[] $publicEvents */
        $publicEvents = $this->publicEvents->keyBy('offerId');
        foreach ($basketItems as $basketItem) {
            $product = $basketItem->product;

            if (!$publicEvents->has($basketItem->offer_id)) {
                throw new Exception("Не найдено билетов для offer_id={$basketItem->offer_id}");
            }

            $tickets = $publicEvents[$basketItem->offer_id]->tickets;
            if ($basketItem->qty != $tickets->count()) {
                throw new Exception("Кол-во билетов для offer_id={$basketItem->offer_id} из корзины не совпадает с чекаутом");
            }

            $ticketIds = [];
            try {
                foreach ($tickets as $ticket) {
                    $ticketDto = new TicketDto();
                    $ticketDto->sprint_id = $product['sprint_id'];
                    $ticketDto->status_id = TicketStatus::STATUS_ACTIVE;
                    $ticketDto->type_id = $product['ticket_type_id'];
                    $ticketDto->first_name = $ticket->firstName;
                    $ticketDto->middle_name = $ticket->middleName;
                    $ticketDto->last_name = $ticket->lastName;
                    $ticketDto->phone = $ticket->phone;
                    $ticketDto->email = $ticket->email;
                    $ticketDto->profession_id = $ticket->professionId;

                    $ticketIds[] = $ticketService->createTicket($ticketDto);
                }


                $basketItem->setTicketIds($ticketIds);
                $basketItem->save();
            } catch (Exception $e) {
                foreach ($ticketIds as $ticketId) {
                    $ticketService->deleteTicket($ticketId);
                }

                throw new Exception("Ошибка при сохранении билета: {$e->getMessage()}");
            }
        }

        try {
            $this->sendTicketNotification($order, $basketItems);
        } catch (Exception $e) {
            logger($e->getMessage(), $e->getTrace());
        }
    }

    private function sendTicketNotification(Order $order, Collection $basketItems)
    {
        $publicEventService = app(PublicEventService::class);
        $publicEventSprintService = app(PublicEventSprintService::class);
        $publicEventMediaService = app(PublicEventMediaService::class);
        $publicEventOrganizerService = app(PublicEventOrganizerService::class);
        $publicEventSprintStageService = app(PublicEventSprintStageService::class);
        $publicEventSpeakerService = app(PublicEventSpeakerService::class);
        $publicEventPlaceService = app(PublicEventPlaceService::class);
        $fileService = app(FileService::class);
        $serviceNotificationService = app(ServiceNotificationService::class);

        $user = $order->getUser();

        $classes = [];
        $pdfs = [];
        foreach($basketItems as $basketItem) {
            $sprint = $publicEventSprintService->find(
                $publicEventSprintService
                    ->query()
                    ->setFilter('id', $basketItem->product['sprint_id'])
            )->first();

            $stages = $publicEventSprintStageService->find(
                $publicEventSprintStageService->query()
                    ->setFilter('id', $sprint->id)
                    ->addSort('time_from')
            )->map(function ($stage) use ($publicEventPlaceService, $publicEventSpeakerService, $publicEventMediaService, $fileService) {
                $place = $publicEventPlaceService->find(
                    $publicEventPlaceService->query()
                        ->setFilter('id', $stage->place_id)
                )->first();

                $placeMedia = $publicEventMediaService->find(
                    $publicEventMediaService->query()
                        ->setFilter('collection', 'default')
                        ->setFilter('media_id', $place->id)
                        ->setFilter('media_type', 'App\Models\PublicEvent\PublicEventPlace')
                )->map(function (MediaDto $media) {
                    return $media->value;
                })->pipe(function (Collection $collection) use ($fileService) {
                    return $fileService->getFiles($collection->toArray())
                        ->map(function ($file) {
                            return $file->absoluteUrl();
                        })
                        ->toArray();
                });

                $speakers = $publicEventSpeakerService->getByStage($stage->id);

                return [
                    sprintf("%s, %s", $place->name, $place->address),
                    Carbon::parse($stage->date)->locale('ru')->isoFormat('D MMMM'),
                    Carbon::parse($stage->time_from)->format('H:m'),
                    Carbon::parse($stage->time_to)->format('H:m'),
                    [
                        'title' => $stage->name,
                        'text' => $stage->description,
                        'kit' => $stage->raider,
                        'speakers' => collect($speakers['items']),
                        'date' => $stage->date,
                        'from' => $stage->time_from,
                        'to' => $stage->time_to
                    ],
                    $place,
                    $placeMedia
                ];
            });

            $event = $publicEventService->findPublicEvents(
                $publicEventService
                    ->query()
                    ->setFilter('id', $sprint->public_event_id)
            )->first();

            $media = $publicEventMediaService->find(
                $publicEventMediaService->query()
                    ->setFilter('collection', 'detail')
                    ->setFilter('media_id', $event->id)
                    ->setFilter('media_type', 'App\Models\PublicEvent\PublicEvent')
            )->first();

            $organizer = $publicEventOrganizerService->find(
                $publicEventOrganizerService->query()
                    ->setFilter('id', $event->organizer_id)
            )->first();

            // Временное решение
            // Здесь нужна компрессия
            $url = $fileService
                ->getFiles([$media->value])
                ->first()
                ->absoluteUrl();

            $link = Link::create(
                $event->name,
                Carbon::createFromDate($stages->first()[4]['date'])->setTimeFromTimeString($stages->first()[4]['from']),
                Carbon::createFromDate($stages->first()[4]['date'])->setTimeFromTimeString($stages->first()[4]['to']),
            )
            ->description($event->description)
            ->address($stages->first()[0]);

            $classes[] = [
                'name' => $event->name,
                'info' => $event->description,
                'price' => (int) $basketItem->price,
                'count' => (int) $basketItem->qty,
                'image' => $url,
                'manager' => [
                    'name' => $organizer->name,
                    'about' => $organizer->description,
                    'phone' => $organizer->phone,
                    'messagers' => false,
                    'email' => $organizer->email,
                    'site' => $organizer->site
                ],
                'apple_wallet' => $link->ics(),
                'google_calendar' => $link->google(),
                'calendar' => $link->ics()
            ];

            $pdfs[] = [
                'name' => $event->name,
                'id' => $event->id,
                'cost' => (int) $basketItem->price,
                'order_num' => $order->id,
                'bought_at' => $order->created_at->locale('ru')->isoFormat('D MMMM, HH:mm'),
                'time' => $stages->map(function ($el) {
                    return sprintf(
                        "%s, %s-%s",
                        $el[1],
                        $el[2],
                        $el[3]
                    );
                })->all(),
                'adress' => $stages->map(function ($el) {
                    return $el[0];
                })->all(),
                'participant' => [
                    'name' => sprintf("%s %s", $user->last_name, $user->first_name),
                    'email' => $user->email,
                    'phone' => $user->phone
                ],
                'manager' => [
                    'name' => $organizer->name,
                    'about' => $organizer->description,
                    'phone' => $organizer->phone,
                    'messangers' => false,
                    'email' => $organizer->email,
                    'site' => $organizer->site
                ],
                'map' => $this->generateMapImage(
                    $stages->map(function ($stage) {
                        return $stage[5];
                    })
                ),
                'routes' => $stages->map(function ($stage) {
                    return [
                        'title' => $stage[0],
                        'text' => $stage[5]->description,
                        'images' => $stage[6]
                    ];
                })->all(),
                'programs' => $stages->map(function ($el) use ($fileService) {
                    return [
                        'title' => $el[4]['title'],
                        'date' => sprintf('%s, %s-%s', $el[1], $el[2], $el[3]),
                        'adress' => $el[0],
                        'text' => $el[4]['text'],
                        'kit' => $el[4]['kit'],
                        'speakers' => ($el[4]['speakers'])->map(function ($speaker) use ($fileService) {
                            return [
                                'name' => sprintf('%s %s', $speaker['first_name'], $speaker['last_name']),
                                'about' => $speaker['description'],
                                'avatar' => $fileService
                                    ->getFiles([$speaker['file_id']])
                                    ->first()
                                    ->absoluteUrl()
                            ];
                        })->all()
                    ];
                })->all()
            ];
        }

        $data = [
            'menu' => [
                'НОВИНКИ' => sprintf('%s/new', config('app.showcase_host')),
                'АКЦИИ' => sprintf('%s/promo', config('app.showcase_host')),
                'ПОДБОРКИ' => sprintf('%s/sets', config('app.showcase_host')),
                'БРЕНДЫ' => sprintf('%s/brands', config('app.showcase_host')),
                'МАСТЕР-КЛАССЫ' => sprintf('%s/masterclasses', config('app.showcase_host')),
            ],
            'title' => sprintf(
                '%s, СПАСИБО ЗА ЗАКАЗ!',
                mb_strtoupper($user->first_name)
            ),
            'text' => sprintf('Заказ <u>%s</u> успешно оплачен,
            <br>Билеты находятся в прикрепленном PDF файле', $order->id),
            'params' => [
                'Получатель' => $user->first_name,
                'Телефон' => $user->phone,
                'Сумма заказа' => (int) $order->price
            ],
            'classes' => $classes
        ];

        if($basketItems->count() > 1) {
            $serviceNotificationService->send(
                $user->id,
                'kupleny_bilety_neskolko_shtuk',
                $data,
                ['pdfs' => ['pdf.ticket' => $pdfs]]
            );
        } else {
            $serviceNotificationService->send(
                $user->id,
                'kuplen_bilet',
                $data,
                ['pdfs' => ['pdf.ticket' => $pdfs]]
            );
        }
    }
    
    private function generateMapImage(Collection $points)
    {
        $query = $points
            ->map(function (PlaceDto $point, $key) {
                return sprintf('%s,%s,%s', $point->longitude, $point->latitude, $key + 1);
            })
            ->join('~');

        return sprintf('https://enterprise.static-maps.yandex.ru/1.x/?key=%s&l=map&pt=%s', config('app.y_maps_key'), $query);
    }

    /**
     * @param Order $order
     * @throws Exception
     */
    private function createPayment(Order $order): void
    {
        $payment = new Payment();
        $payment->payment_method = $this->paymentMethodId;
        $payment->payment_system = PaymentSystem::YANDEX;
        $payment->order_id = $order->id;
        $payment->sum = $this->price;

        $writer = new OrderWriter();
        $writer->setPayments($order, collect([$payment]));
    }

    private function basket(): ?Basket
    {
        static $basket = null;
        if (!$basket) {
            $basket = Basket::query()
                ->where('id', $this->basketId)
                ->with('items')
                ->first();
        }

        return $basket;
    }

    private function offerToBasketMap(): array
    {
        $result = [];
        $basket = $this->basket();
        foreach ($basket->items as $basketItem) {
            $key = $basketItem->bundle_id ?
                $basketItem->offer_id . ':' . $basketItem->bundle_id :
                $basketItem->offer_id;
            $result[$key] = $basketItem->id;
        }
        return $result;
    }
}
