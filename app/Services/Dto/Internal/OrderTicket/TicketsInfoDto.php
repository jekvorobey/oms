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
    /** @var float */
    public $price;
    /** @var float */
    public $pricePerOne;
    /** @var int */
    public $ticketsQty;
    /** @var Collection|TicketInfoDto[] */
    public $tickets;

    /**
     * TicketsInfoDto constructor.
     */
    public function __construct()
    {
        $this->tickets = collect();
    }

    /**
     * @param  TicketInfoDto  $ticketDto
     */
    public function addTicket(TicketInfoDto $ticketDto): void
    {
        $this->tickets->push($ticketDto);
    }
}
