<?php

namespace App\Services\PublicEventService\Email;

/**
 * Class PublicEventCartStruct
 * @package App\Services\PublicEventService\Email
 */
class PublicEventCartStruct
{
    /** @var int */
    public $id;
    /** @var string */
    public $name;
    /** @var int */
    public $sprintId;
    /** @var array|array[] */
    public $speakers;
    /** @var array */
    public $organizer;
    /** @var string */
    public $code;
    /** @var string */
    public $dateFrom;
    /** @var string */
    public $dateTo;
    /** @var mixed */
    public $image;
    /** @var bool */
    public $active;
    /** @var string */
    public $nearestDate;
    /** @var string */
    public $nearestTimeFrom;
    /** @var string */
    public $nearestPlaceName;
    /** @var array */
    public $places;
    /** @var array */
    public $stages;
    /** @var array */
    public $offerIds;
    /** @var bool */
    public $availableForSale;
}
