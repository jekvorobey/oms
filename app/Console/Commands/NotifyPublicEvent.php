<?php

namespace App\Console\Commands;

use App\Models\Basket\BasketItem;
use App\Models\Order\OrderStatus;
use Carbon\Carbon;
use Greensight\CommonMsa\Services\AuthService\UserService;
use Greensight\Customer\Dto\CustomerDto;
use Greensight\Customer\Services\CustomerService\CustomerService;
use Greensight\Message\Services\ServiceNotificationService\ServiceNotificationService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Pim\Dto\PublicEvent\PublicEventDto;
use Pim\Dto\PublicEvent\PublicEventSpeakerDto;
use Pim\Dto\PublicEvent\PublicEventTypeDto;
use Pim\Dto\PublicEvent\SprintDto;
use Pim\Dto\PublicEvent\StageDto;
use Pim\Services\PublicEventOrganizerService\PublicEventOrganizerService;
use Pim\Services\PublicEventService\PublicEventService;
use Pim\Services\PublicEventSpeakerService\PublicEventSpeakerService;
use Pim\Services\PublicEventSprintService\PublicEventSprintService;
use Pim\Services\PublicEventSprintStageService\PublicEventSprintStageService;
use Pim\Services\PublicEventTypeService\PublicEventTypeService;

class NotifyPublicEvent extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notify:public-event';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Напомнить о событии за 3 дня';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(
        PublicEventService $publicEventService,
        PublicEventSprintService $publicEventSprintService,
        PublicEventSprintStageService $publicEventSprintStageService,
        PublicEventSpeakerService $publicEventSpeakerService,
        PublicEventTypeService $publicEventTypeService,
        PublicEventOrganizerService $publicEventOrganizerService,
        ServiceNotificationService $serviceNotificationService,
        UserService $userService,
        CustomerService $customerService
    ) {
        $publicEventService->findPublicEvents(
            $publicEventService->query()
                ->addSort('created_at', 'desc')
        )
        ->map(function (PublicEventDto $publicEvent) use ($publicEventSprintService) {
            $sprint = $publicEventSprintService->find(
                $publicEventSprintService->query()
                    ->setFilter('public_event_id', $publicEvent->id)
                    ->addSort('date_start')
            )->first();

            return [
                'event' => $publicEvent,
                'sprint' => $sprint
            ];
        })
        ->filter(function ($items) {
            return $items['event'] !== null && $items['sprint'] !== null;
        })
        ->filter(function ($items) {
            return $items['sprint']->date_start !== null;
        })
        ->filter(function ($items) {
            return Carbon::today()->startOfDay()->addDays(3) == Carbon::parse($items['sprint']->date_start)->startOfDay();
        })
        ->map(function ($items) use ($customerService) {
            $basketItems = BasketItem::query()
                ->where('product->sprint_id', $items['sprint']->id)
                ->with('basket')
                ->get()
                ->toBase()
                ->pluck('basket.customer_id');

            return [
                'customers' => $basketItems
                    ->unique()
                    ->map(function ($id) use ($customerService) {
                        return $customerService->customers(
                            $customerService->newQuery()
                                ->setFilter('id', $id)
                        )->first();
                    }),
                'sprint' => $items['sprint'],
                'event' => $items['event']
            ];
        })
        ->filter(function ($items) {
            return $items['customers']->isNotEmpty();
        })
        ->map(function ($items) use ($publicEventSprintStageService, $publicEventSpeakerService) {
            $items['speakers'] = $publicEventSprintStageService->find(
                $publicEventSprintStageService->query()
                    ->setFilter('sprint_id', 1)
            )
            ->map(function (StageDto $stageDto) use ($publicEventSpeakerService) {
                return collect($publicEventSpeakerService->getByStage($stageDto->id)["items"])
                    ->map(function ($speaker) {
                        return sprintf('%s %s', $speaker['first_name'], $speaker['last_name']);
                    });
            })
            ->flatten()
            ->unique()
            ->join(', ');

            return $items;
        })
        ->each(function ($items) use ($publicEventTypeService, $serviceNotificationService, $userService, $publicEventSprintStageService, $publicEventOrganizerService) {
            $date = Carbon::parse($items['event']->date_start)->locale('ru');
            $stage = $publicEventSprintStageService->find(
                $publicEventSprintStageService->query()
                    ->setFilter('sprint_id', $items['sprint']->id)
                    ->addSort('date')
                    ->addSort('time_from')
            )->first();
            $organizer = $publicEventOrganizerService->find(
                $publicEventOrganizerService->query()
                    ->setFilter('id', $items['event']->organizer_id)
            )->first();

            $items['customers']->each(function ($customer) use (
                $publicEventTypeService,
                $serviceNotificationService,
                $userService,
                $items,
                $date,
                $stage,
                $organizer
            ) {
                $serviceNotificationService->send(
                    $customer->user_id,
                    'napominanie_o_meropriyatii_za_3_dnya',
                    [
                        'CLASS_TYPE' => $publicEventTypeService->find(
                            $publicEventTypeService->query()
                                ->setFilter('id', $items['event']->type_id)
                        )->first()->name,
                        'CUSTOMER_NAME' => $userService->users(
                            $userService->newQuery()
                                ->setFilter('id', $customer->user_id)
                        )->first()->first_name,
                        'NAME_CLASS' => $items['event']->name,
                        'CLASS_NUMBER' => '',
                        'TICKETS_DAY' => $date->isoFormat('D MMMM'),
                        'DATE' => Carbon::parse($stage->date)->locale('ru')->isoFormat('D MMMM'),
                        'START_TIME' => $stage->time_from,
                        'SPEAKERS' => $items['speakers'],
                        'CALL_ORG' => $organizer['phone'],
                        'MAIL_ORG' => $organizer['email'],
                        'CLASS_ORDER_TEXT' => $items['event']->description
                    ]
                );
            });
        });
    }
}
