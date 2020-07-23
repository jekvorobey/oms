<?php

namespace App\Services\Dto\Internal\PublicEventOrder;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Class TicketInfoDto
 * @package App\Services\Dto\Internal\OrderTicket
 */
class TicketInfoDto implements Arrayable
{
    /** @var int */
    public $id;
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

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->firstName,
            'middle_name' => $this->middleName,
            'last_name' => $this->lastName,
            'phone' => $this->phone,
            'email' => $this->email,
        ];
    }
}
