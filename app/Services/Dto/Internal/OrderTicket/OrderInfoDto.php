<?php

namespace App\Services\Dto\Internal\OrderTicket;

use Illuminate\Support\Collection;

/**
 * Class OrderInfoDto
 * @package App\Services\Dto\Internal\OrderTicket
 */
class OrderInfoDto
{
    /** @var int */
    public $id;
    /** @var string */
    public $number;
    /** @var double */
    public $price;
    /** @var Collection|PublicEventInfoDto[] */
    public $publicEvents;

    /**
     * OrderInfoDto constructor.
     */
    public function __construct()
    {
        $this->publicEvents = collect();
    }
}
