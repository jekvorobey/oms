<?php

namespace App\Services\Dto\Internal\OrderTicket;

use Illuminate\Support\Collection;

/**
 * Class PublicEventInfoDto
 * @package App\Services\Dto\Internal\OrderTicket
 */
class PublicEventInfoDto
{
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

    /**
     * @param  SpeakerInfoDto  $speakerInfoDto
     */
    public function addSpeaker(SpeakerInfoDto $speakerInfoDto): void
    {
        $this->speakers->put($speakerInfoDto->id, $speakerInfoDto);
    }

    /**
     * @param  PlaceInfoDto  $placeInfoDto
     */
    public function addPlace(PlaceInfoDto $placeInfoDto): void
    {
        $this->places->put($placeInfoDto->id, $placeInfoDto);
    }

    /**
     * @param  StageInfoDto  $stageInfoDto
     */
    public function addStage(StageInfoDto $stageInfoDto): void
    {
        $this->stages->push($stageInfoDto);
    }

    /**
     * @param  TicketsInfoDto  $ticketsInfoDto
     */
    public function addTicketInfo(TicketsInfoDto $ticketsInfoDto): void
    {
        $this->ticketsInfo->push($ticketsInfoDto);
    }
}
