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
    public $tickets;
    /** @var OrganizerInfoDto */
    public $organizer;

    /**
     * PublicEventInfoDto constructor.
     */
    public function __construct()
    {
        $this->tickets = collect();
    }
}
