<?php

namespace App\Core\Checkout;

use Illuminate\Support\Collection;

/**
 * Class CheckoutPublicEvent
 * @package App\Core\Checkout
 */
class CheckoutPublicEvent
{
    /** @var int */
    public $offerId;

    /** @var Collection|CheckoutTicket[] */
    public $tickets;

    public static function fromArray(array $data): self
    {
        $publicEvent = new self();
        @([
            'offerId' => $publicEvent->offerId,
            'tickets' => $tickets,
        ] = $data);

        $publicEvent->tickets = collect();
        foreach ($tickets as $ticketData) {
            $publicEvent->tickets->push(CheckoutTicket::fromArray($ticketData));
        }

        return $publicEvent;
    }
}
