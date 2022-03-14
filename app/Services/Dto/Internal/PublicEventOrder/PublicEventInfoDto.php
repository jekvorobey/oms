<?php

namespace App\Services\Dto\Internal\PublicEventOrder;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;

/**
 * Class PublicEventInfoDto
 * @package App\Services\Dto\Internal\OrderTicket
 */
class PublicEventInfoDto implements Arrayable
{
    /** @var int */
    public $id;
    /** @var string */
    public $code;
    /** @var string */
    public $dateFrom;
    /** @var string */
    public $dateTo;
    /** @var Collection|SpeakerInfoDto[] */
    public $speakers;
    /** @var Collection|PlaceInfoDto[] */
    public $places;
    /** @var Collection|StageInfoDto[] */
    public $stages;
    /** @var Collection|TicketsInfoDto[] */
    public $ticketsInfo;
    /** @var OrganizerInfoDto */
    public $organizer;
    /** @var bool */
    public $active;
    /** @var bool */
    public $availableForSale;

    /**
     * PublicEventInfoDto constructor.
     */
    public function __construct()
    {
        $this->speakers = collect();
        $this->places = collect();
        $this->stages = collect();
        $this->ticketsInfo = collect();
    }

    public function addSpeaker(SpeakerInfoDto $speakerInfoDto): void
    {
        $this->speakers->put($speakerInfoDto->id, $speakerInfoDto);
    }

    public function addPlace(PlaceInfoDto $placeInfoDto): void
    {
        $this->places->put($placeInfoDto->id, $placeInfoDto);
    }

    public function addStage(StageInfoDto $stageInfoDto): void
    {
        $this->stages->push($stageInfoDto);
    }

    public function addTicketInfo(TicketsInfoDto $ticketsInfoDto): void
    {
        $this->ticketsInfo->push($ticketsInfoDto);
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'dateFrom' => $this->dateFrom,
            'dateTo' => $this->dateTo,
            'speakers' => $this->speakers->map(function (SpeakerInfoDto $speakerInfoDto) {
                return $speakerInfoDto->toArray();
            })->values()->toArray(),
            'ticketsInfo' => $this->ticketsInfo->map(function (TicketsInfoDto $ticketsInfoDto) {
                return $ticketsInfoDto->toArray();
            })->toArray(),
        ];
    }
}
