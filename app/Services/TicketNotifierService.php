<?php

namespace App\Services;

use App\Models\Order\Order;
use Carbon\Carbon;
use Greensight\CommonMsa\Services\FileService\FileService;
use Greensight\Message\Services\ServiceNotificationService\ServiceNotificationService;
use Illuminate\Support\Collection;
use Pim\Dto\PublicEvent\MediaDto;
use Pim\Dto\PublicEvent\PlaceDto;
use Pim\Services\PublicEventMediaService\PublicEventMediaService;
use Pim\Services\PublicEventOrganizerService\PublicEventOrganizerService;
use Pim\Services\PublicEventPlaceService\PublicEventPlaceService;
use Pim\Services\PublicEventService\PublicEventService;
use Pim\Services\PublicEventSpeakerService\PublicEventSpeakerService;
use Pim\Services\PublicEventSprintService\PublicEventSprintService;
use Pim\Services\PublicEventSprintStageService\PublicEventSprintStageService;
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

    /** @var PublicEventPlaceService */
    protected $publicEventPlaceService;

    /** @var FileService */
    protected $fileService;

    /** @var ServiceNotificationService */
    protected $serviceNotificationService;

    public function __construct(
        PublicEventService $publicEventService,
        PublicEventSprintService $publicEventSprintService,
        PublicEventMediaService $publicEventMediaService,
        PublicEventOrganizerService $publicEventOrganizerService,
        PublicEventSprintStageService $publicEventSprintStageService,
        PublicEventSpeakerService $publicEventSpeakerService,
        PublicEventPlaceService $publicEventPlaceService,
        FileService $fileService,
        ServiceNotificationService $serviceNotificationService
    ) {
        $this->publicEventService = $publicEventService;
        $this->publicEventSprintService = $publicEventSprintService;
        $this->publicEventMediaService = $publicEventMediaService;
        $this->publicEventOrganizerService = $publicEventOrganizerService;
        $this->publicEventSprintStageService = $publicEventSprintStageService;
        $this->publicEventSpeakerService = $publicEventSpeakerService;
        $this->publicEventPlaceService = $publicEventPlaceService;
        $this->fileService = $fileService;
        $this->serviceNotificationService = $serviceNotificationService;
    }

    public function notify(Order $order)
    {
        $basketItems = $order->basket->items;
        $user = $order->getUser();

        $classes = [];
        $pdfs = [];
        foreach($basketItems as $basketItem) {
            $sprint = $this->publicEventSprintService->find(
                $this->publicEventSprintService
                    ->query()
                    ->setFilter('id', $basketItem->product['sprint_id'])
            )->first();

            $stages = $this->publicEventSprintStageService->find(
                $this->publicEventSprintStageService
                    ->query()
                    ->setFilter('sprint_id', $sprint->id)
                    ->addSort('time_from')
            )->map(function ($stage) {
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

                return [
                    sprintf("%s, %s", $place->name, $place->address),
                    Carbon::parse($stage->date)->locale('ru')->isoFormat('D MMMM (ddd)'),
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

            $event = $this->publicEventService->findPublicEvents(
                $this->publicEventService
                    ->query()
                    ->setFilter('id', $sprint->public_event_id)
            )->first();

            $media = $this->publicEventMediaService->find(
                $this->publicEventMediaService->query()
                    ->setFilter('collection', 'detail')
                    ->setFilter('media_id', $event->id)
                    ->setFilter('media_type', 'App\Models\PublicEvent\PublicEvent')
            )->first();

            $organizer = $this->publicEventOrganizerService->find(
                $this->publicEventOrganizerService->query()
                    ->setFilter('id', $event->organizer_id)
            )->first();

            // Временное решение
            // Здесь нужна компрессия
            $url = $this
                ->fileService
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
                'count' => sprintf('%s %s', (int) $basketItem->qty, $this->generateTicketWord((int) $basketItem->qty)),
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
                'name' => sprintf('%s (%s)', $event->name, $basketItem->product['ticket_type_name']),
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
                    'name' => $order->receiver_name,
                    'email' => $order->receiver_email,
                    'phone' => $order->receiver_phone
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
                'programs' => $stages->map(function ($el) {
                    return [
                        'title' => $el[4]['title'],
                        'date' => sprintf('%s, %s-%s', $el[1], $el[2], $el[3]),
                        'adress' => $el[0],
                        'text' => $el[4]['text'],
                        'kit' => $el[4]['kit'],
                        'speakers' => ($el[4]['speakers'])->map(function ($speaker) {
                            return [
                                'name' => sprintf('%s %s', $speaker['first_name'], $speaker['last_name']),
                                'about' => $speaker['description'],
                                'avatar' => $this
                                    ->fileService
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

        if($order->receiver_email != $user->email) {
            foreach($pdfs as $pdf) {
                $this->serviceNotificationService->sendFile(
                    'Куплен билет',
                    $order->receiver_email,
                    $order->receiver_name,
                    'pdf.ticket',
                    $pdf
                );
            }
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

    private function generateTicketWord(int $count)
    {
        if($count == 1) {
            return 'билет';
        }

        if($count > 1 && $count < 5) {
            return 'билета';
        }

        return 'билетов';
    }

    public function test()
    {
        $this->notify(Order::find(797));
    }
}
