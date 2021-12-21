<?php

namespace App\Services;

use App\Models\Order\Order;
use App\Observers\Order\OrderObserver;
use App\Services\DocumentService\OrderTicketsCreator;
use Carbon\Carbon;
use Greensight\CommonMsa\Services\FileService\FileService;
use Greensight\Message\Services\ServiceNotificationService\ServiceNotificationService;
use Greensight\Customer\Services\CustomerService\CustomerService;
use Illuminate\Support\Collection;
use Pim\Dto\PublicEvent\MediaDto;
use Pim\Dto\PublicEvent\OrganizerDto;
use Pim\Dto\PublicEvent\PlaceDto;
use Pim\Dto\PublicEvent\PublicEventDto;
use Pim\Dto\PublicEvent\StageDto;
use Pim\Dto\PublicEvent\TicketDto;
use Pim\Services\PublicEventMediaService\PublicEventMediaService;
use Pim\Services\PublicEventOrganizerService\PublicEventOrganizerService;
use Pim\Services\PublicEventPlaceService\PublicEventPlaceService;
use Pim\Services\PublicEventService\PublicEventService;
use Pim\Services\PublicEventSpeakerService\PublicEventSpeakerService;
use Pim\Services\PublicEventProfessionService\PublicEventProfessionService;
use Pim\Services\PublicEventSprintService\PublicEventSprintService;
use Pim\Services\PublicEventSprintStageService\PublicEventSprintStageService;
use Pim\Services\PublicEventTicketService\PublicEventTicketService;
use Pim\Services\PublicEventTypeService\PublicEventTypeService;
use Spatie\CalendarLinks\Link;

class TicketNotifierService
{
    /** @var PublicEventService */
    protected $publicEventService;

    /** @var PublicEventSprintService */
    protected $publicEventSprintService;

    /** @var PublicEventMediaService */
    protected $publicEventMediaService;

    /** @var PublicEventOrganizerService */
    protected $publicEventOrganizerService;

    /** @var PublicEventSprintStageService */
    protected $publicEventSprintStageService;

    /** @var PublicEventSpeakerService */
    protected $publicEventSpeakerService;

    /** @var PublicEventProfessionService */
    protected $publicEventProfessionService;

    /** @var PublicEventPlaceService */
    protected $publicEventPlaceService;

    /** @var PublicEventTicketService */
    protected $publicEventTicketService;

    /** @var FileService */
    protected $fileService;

    /** @var ServiceNotificationService */
    protected $serviceNotificationService;

    /** @var PublicEventTypeService */
    protected $publicEventTypeService;

    /** @var OrderTicketsCreator */
    protected $orderTicketsCreator;

    public function __construct(
        PublicEventService $publicEventService,
        PublicEventSprintService $publicEventSprintService,
        PublicEventMediaService $publicEventMediaService,
        PublicEventOrganizerService $publicEventOrganizerService,
        PublicEventSprintStageService $publicEventSprintStageService,
        PublicEventSpeakerService $publicEventSpeakerService,
        PublicEventProfessionService $publicEventProfessionService,
        PublicEventPlaceService $publicEventPlaceService,
        PublicEventTicketService $publicEventTicketService,
        PublicEventTypeService $publicEventTypeService,
        FileService $fileService,
        ServiceNotificationService $serviceNotificationService,
        OrderTicketsCreator $orderTicketsCreator,
        CustomerService $customerService
    ) {
        $this->publicEventService = $publicEventService;
        $this->publicEventSprintService = $publicEventSprintService;
        $this->publicEventMediaService = $publicEventMediaService;
        $this->publicEventOrganizerService = $publicEventOrganizerService;
        $this->publicEventSprintStageService = $publicEventSprintStageService;
        $this->publicEventSpeakerService = $publicEventSpeakerService;
        $this->publicEventProfessionService = $publicEventProfessionService;
        $this->publicEventPlaceService = $publicEventPlaceService;
        $this->publicEventTicketService = $publicEventTicketService;
        $this->publicEventTypeService = $publicEventTypeService;
        $this->fileService = $fileService;
        $this->serviceNotificationService = $serviceNotificationService;
        $this->orderTicketsCreator = $orderTicketsCreator;
        $this->customerService = $customerService;
    }

    public function notify(Order $order)
    {
        $basketItems = $order->basket->items;
        $user = $order->getUser();

        /** @var PublicEventDto $firstEvent */
        $firstEvent = null;

        /** @var OrganizerDto $firstOrganizer */
        $firstOrganizer = null;

        $classes = [];
        $pdfs = [];
        foreach ($basketItems as $basketItem) {
            $sprint = $this->publicEventSprintService->find(
                $this->publicEventSprintService
                    ->query()
                    ->setFilter('id', $basketItem->product['sprint_id'])
            )->first();

            $stages = $this->publicEventSprintStageService->find(
                $this->publicEventSprintStageService
                    ->query()
                    ->setFilter('sprint_id', $sprint->id)
                    ->addSort('date_from')
            )->map(function (StageDto $stage) {
                $place = $this->publicEventPlaceService->find(
                    $this->publicEventPlaceService->query()
                        ->setFilter('id', $stage->place_id)
                )->first();

                $placeMedia = $this->publicEventMediaService->find(
                    $this->publicEventMediaService->query()
                        ->setFilter('collection', 'default')
                        ->setFilter('media_id', $place->id)
                        ->setFilter('media_type', 'App\Models\PublicEvent\PublicEventPlace')
                )->map(function (MediaDto $media) {
                    return $media->value;
                })->pipe(function (Collection $collection) {
                    return $this->fileService
                        ->getFiles($collection->toArray())
                        ->map(function ($file) {
                            return $file->absoluteUrl();
                        })
                        ->toArray();
                });

                $speakers = $this->publicEventSpeakerService
                    ->getByStage($stage->id);

                $dateFormatted = Carbon::parse($stage->date_from)->locale('ru')->isoFormat('D MMMM (dd)');
                if ($stage->date_to !== $stage->date_from) {
                    $dateFormatted .= ' - ' . Carbon::parse($stage->date_to)->locale('ru')->isoFormat('D MMMM (dd)');
                }
                return [
                    sprintf('%s, %s', $place->name, $place->address),
                    $dateFormatted,
                    Carbon::parse($stage->time_from)->format('H:i'),
                    Carbon::parse($stage->time_to)->format('H:i'),
                    [
                        'title' => $stage->name,
                        'text' => $stage->description,
                        'kit' => $stage->raider,
                        'speakers' => collect($speakers['items']),
                        'date_from' => $stage->date_from,
                        'date_to' => $stage->date_to,
                        'from' => $stage->time_from,
                        'to' => $stage->time_to,
                    ],
                    $place,
                    $placeMedia,
                ];
            });

            $event = $this->publicEventService->findPublicEvents(
                $this->publicEventService
                    ->query()
                    ->setFilter('id', $sprint->public_event_id)
            )->first();

            if ($firstEvent == null) {
                $firstEvent = $event;
            }

            $media = $this->publicEventMediaService->find(
                $this->publicEventMediaService->query()
                    ->setFilter('collection', 'catalog')
                    ->setFilter('media_id', $event->id)
                    ->setFilter('media_type', 'App\Models\PublicEvent\PublicEvent')
            )->first();

            $organizer = $this->publicEventOrganizerService->find(
                $this->publicEventOrganizerService->query()
                    ->setFilter('id', $event->organizer_id)
            )->first();

            if ($firstOrganizer == null) {
                $firstOrganizer = $organizer;
            }

            // Временное решение
            // Здесь нужна компрессия
            // $url = $this
            //     ->fileService
            //     ->getFiles([$media->value])
            //     ->first()
            //     ->absoluteUrl();
            $url = sprintf('%s/files/compressed/%d/288/192/orig', config('app.showcase_host'), $media->value);

            $event_desc = strip_tags($event->description);
            preg_match('/([^.!?]+[.!?]+){3}/', $event_desc, $event_desc_short, PREG_OFFSET_CAPTURE, 0);

            $link = Link::create(
                $event->name,
                Carbon::createFromDate($stages->first()[4]['date_from'])->setTimeFromTimeString($stages->first()[4]['from']),
                Carbon::createFromDate($stages->first()[4]['date_to'])->setTimeFromTimeString($stages->first()[4]['to']),
            )
            ->description($event_desc_short[0][0] ?? '')
            ->address($stages->first()[0]);

            $programs = $stages->map(function ($el) {
                return [
                    'title' => $el[4]['title'],
                    'date' => sprintf('%s, %s-%s', $el[1], $el[2], $el[3]),
                    'adress' => $el[0],
                    'text' => $el[4]['text'],
                    'kit' => $el[4]['kit'],
                    'speakers' => $el[4]['speakers']->map(function ($speaker) {
                        $activity = $this->customerService->activities()->setIds([
                            $speaker['profession_id'],
                        ])->load()->first();

                        return [
                            'name' => sprintf('%s %s', $speaker['first_name'], $speaker['last_name']),
                            'profession' => $activity->name,
                            'about' => $speaker['description'],
                            'file_id' => $speaker['file_id'],
                            'avatar' => $this
                                ->fileService
                                ->getFiles([$speaker['file_id']])
                                ->first()
                                ->absoluteUrl(),
                        ];
                    })->all(),
                ];
            })->all();

            $speakerIdx = empty($programs[0]['speakers']) ? 1 : 0;

            $classes[] = [
                'name' => $event->name,
                'info' => $event->description,
                'speaker_info' => $programs[$speakerIdx]['speakers'][0]['name'] . ', ' . $programs[$speakerIdx]['speakers'][0]['profession'],
                'ticket_type' => '(' . $basketItem->product['ticket_type_name'] . ')',
                'price' => price_format((int) $basketItem->price),
                'nearest_date' => $stages->map(function ($el) {
                    return sprintf(
                        '%s, %s-%s',
                        $el[1],
                        $el[2],
                        $el[3]
                    );
                })->first(),
                'count' => sprintf('%s %s', (int) $basketItem->qty, $this->generateTicketWord((int) $basketItem->qty)),
                'image' => $url,
                'manager' => [
                    'name' => $organizer->name,
                    'about' => $organizer->description,
                    'phone' => $organizer->phone,
                    'messagers' => false,
                    'email' => $organizer->email,
                    'site' => $organizer->site,
                ],
                'apple_wallet' => $link->ics(),
                'google_calendar' => $link->google(),
                'calendar' => $link->ics(),
            ];

            foreach ($basketItem->product['ticket_ids'] as $ticket) {
                /** @var TicketDto $ticket */
                $ticket = $this->publicEventTicketService->tickets(
                    $this->publicEventTicketService
                        ->newQuery()
                        ->setFilter('id', $ticket)
                )->first();

                $pdfs[] = [
                    'RECEIVER_EMAIL' => $ticket->email,
                    'RECEIVER_NAME' => $ticket->first_name,
                    'MESSAGE_FILENAME' => sprintf('order-tickets-%s', $ticket->code),
                    'name' => $event->name,
                    'ticket_type' => '(' . $basketItem->product['ticket_type_name'] . ')',
                    'id' => $ticket->code,
                    'cost' => price_format((int) $basketItem->price),
                    'order_num' => $order->number,
                    'bought_at' => $order->created_at->locale('ru')->isoFormat('D MMMM, HH:mm'),
                    'time' => $stages->map(function ($el) {
                        return sprintf(
                            '%s, %s-%s',
                            $el[1],
                            $el[2],
                            $el[3]
                        );
                    })->all(),
                    'adress' => $stages->map(function ($el) {
                        return $el[0];
                    })->unique()->all(),
                    'participant' => [
                        'name' => sprintf('%s %s', $ticket->last_name, $ticket->first_name),
                        'short_name' => mb_substr($ticket->first_name, 0, 1) . mb_substr($ticket->last_name, 0, 1),
                        'email' => $ticket->email,
                        'phone' => OrderObserver::formatNumber($ticket->phone),
                    ],
                    'manager' => [
                        'name' => $organizer->name,
                        'about' => $organizer->description,
                        'phone' => $organizer->phone,
                        'messangers' => false,
                        'email' => $organizer->email,
                        'site' => $organizer->site,
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
                            'images' => $stage[6],
                        ];
                    })->unique('title')->all(),
                    'programs' => $programs,
                ];
            }
        }

        $type = $this->publicEventTypeService->find(
            $this->publicEventTypeService->query()
                ->setFilter('id', $firstEvent->type_id)
        )->first();

        $firstItem = $basketItems->first()->id;
        $document = $this->orderTicketsCreator->setOrder($order)->setBasketItemId($firstItem)->create();

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
            <br>Билеты находятся в прикрепленном pdf файле', $order->number),
            'params' => [
                'Получатель' => $order->receiver_name,
                'Телефон' => OrderObserver::formatNumber($order->receiver_phone),
                'Сумма заказа' => $order->price > 0 ? sprintf('%s ₽', (int) $order->price) : 'Бесплатно',
            ],
            'classes' => $classes,
            'CUSTOMER_NAME' => $user->first_name,
            'ORDER_ID' => $order->number,
            'CLASS_TYPE' => $type->name,
            'NAME_CLASS' => $firstEvent->name,
            'LINK_ACCOUNT' => sprintf('%s/profile', config('app.showcase_host')),
            'CALL_ORG' => $firstOrganizer->phone,
            'MAIL_ORG' => $firstOrganizer->email,
            'LINK_ORDER' => sprintf('%s/profile/orders/%d', config('app.showcase_host'), $order->id),
            'LINK_TICKET' => (string) OrderObserver::shortenLink(
                $this->fileService
                    ->getFiles([$document->file_id])
                    ->first()
                    ->absoluteUrl()
            ),
            'REFUND_ORDER' => sprintf('%s ₽', (int) optional($order->orderReturns->first())->price),
            'NUMBER_TICKET' => optional(optional($order->orderReturns->first())->items)->count() ?? 0,
        ];

        if ($basketItems->count() > 1) {
            $this->serviceNotificationService->send(
                $user->id,
                'kupleny_bilety_neskolko_shtuk',
                $data,
                ['pdfs' => ['pdf.ticket' => $pdfs]]
            );
        } else {
            $this->serviceNotificationService->send(
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
                return sprintf('%s,%s,pm2ntm%s', $point->longitude, $point->latitude, $key + 1);
            })
            ->join('~');

        return sprintf('https://enterprise.static-maps.yandex.ru/1.x/?key=%s&l=map&pt=%s', config('app.y_maps_key'), $query);
    }

    private function generateTicketWord(int $count)
    {
        if ($count == 1) {
            return 'билет';
        }

        if ($count > 1 && $count < 5) {
            return 'билета';
        }

        return 'билетов';
    }

    public function test()
    {
        $this->notify(Order::find(797));
    }
}
