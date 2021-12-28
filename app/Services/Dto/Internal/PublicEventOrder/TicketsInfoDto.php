<?php

namespace App\Services\Dto\Internal\PublicEventOrder;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Class PublicEventInfoDto
 * @package App\Mail\PublicEvent\SendTicket\Dto
 */
class TicketsInfoDto implements Arrayable
{
    /** @var int */
    public $id;
    /** @var int */
    public $photoId;
    /** @var string */
    public $name;
    /** @var int[] */
    public $stageIds;
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

    public function addTicket(TicketInfoDto $ticketDto): void
    {
        $this->tickets->push($ticketDto);
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'photo_id' => $this->photoId,
            'name' => $this->name,
            'stage_ids' => $this->stageIds,
            'ticket_type_name' => $this->ticketTypeName,
            'nearest_date' => $this->nearestDate,
            'nearest_time_from' => $this->nearestTimeFrom,
            'nearest_place_name' => $this->nearestPlaceName,
            'price' => $this->price,
            'tickets_qty' => $this->ticketsQty,
            'tickets' => $this->tickets->map(function (TicketInfoDto $ticketDto) {
                return $ticketDto->toArray();
            })->toArray(),
        ];
    }
}
