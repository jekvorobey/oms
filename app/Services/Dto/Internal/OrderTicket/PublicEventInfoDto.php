<?php

namespace App\Services\Dto\Internal\OrderTicket;

use Illuminate\Support\Collection;

/**
 * Class PublicEventInfoDto
 * @package App\Services\Dto\Internal\OrderTicket
 */
class PublicEventInfoDto
{
    /** @var Collection|TicketsInfoDto[] */
    public $ticketsInfo;
    /** @var OrganizerInfoDto */
    public $organizer;

    /**
     * PublicEventInfoDto constructor.
     */
    public function __construct()
    {
        $this->ticketsInfo = collect();
    }

    /**
     * @param  TicketsInfoDto  $ticketsInfoDto
     */
    public function addTicketInfo(TicketsInfoDto $ticketsInfoDto): void
    {
        $this->ticketsInfo->push($ticketsInfoDto);
    }
}
