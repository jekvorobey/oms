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
    /** @var int */
    public $ticketsQty;
}
