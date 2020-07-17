<?php

namespace App\Services\Dto\Internal\OrderTicket;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Class PublicEventInfoDto
 * @package App\Mail\PublicEvent\SendTicket\Dto
 */
class TicketsInfoDto
{
    /** @var int */
    public $photoId;
    /** @var string */
    public $name;
    /** @var string */
    public $ticketTypeName;
    /** @var Carbon */
    public $nearestDate;
    /** @var Carbon */
    public $nearestTimeFrom;
    /** @var string */
    public $nearestPlaceName;
    /** @var Collection|SpeakerInfoDto[] */
    public $speakers;
    /** @var float */
    public $price;
    /** @var float */
    public $pricePerOne;
    /** @var int */
    public $ticketsQty;
    /** @var Collection|TicketDto[] */
    public $tickets;

    /**
     * TicketsInfoDto constructor.
     */
    public function __construct()
    {
        $this->speakers = collect();
        $this->tickets = collect();
    }

    /**
     * @param  SpeakerInfoDto  $speakerInfoDto
     */
    public function addSpeaker(SpeakerInfoDto $speakerInfoDto): void
    {
        $this->speakers->push($speakerInfoDto);
    }

    /**
     * @param  TicketDto  $ticketDto
     */
    public function addTicket(TicketDto $ticketDto): void
    {
        $this->tickets->push($ticketDto);
    }
}
