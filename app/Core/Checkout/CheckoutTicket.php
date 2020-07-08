<?php

namespace App\Core\Checkout;

/**
 * Class CheckoutTicket
 * @package App\Core\Checkout
 */
class CheckoutTicket
{
    /** @var string */
    public $firstName;
    /** @var string */
    public $middleName;
    /** @var string */
    public $lastName;
    /** @var string */
    public $phone;
    /** @var string */
    public $email;
    /** @var int */
    public $professionId;

    /**
     * @param  array  $data
     * @return static
     */
    public static function fromArray(array $data): self
    {
        $ticket = new self();
        @([
            'firstName' => $ticket->firstName,
            'middleName' => $ticket->middleName,
            'lastName' => $ticket->lastName,
            'phone' => $ticket->phone,
            'email' => $ticket->email,
            'professionId' => $ticket->professionId,
        ] = $data);

        return $ticket;
    }
}
