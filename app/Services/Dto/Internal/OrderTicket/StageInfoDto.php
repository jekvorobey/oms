<?php

namespace App\Services\Dto\Internal\OrderTicket;

use Illuminate\Support\Carbon;

/**
 * Class StageInfoDto
 * @package App\Services\Dto\Internal\OrderTicket
 */
class StageInfoDto
{
    /** @var int */
    public $id;
    /** @var string */
    public $name;
    /** @var string */
    public $description;
    /** @var string */
    public $result;
    /** @var string */
    public $raider;
    /** @var Carbon */
    public $date;
    /** @var Carbon */
    public $timeFrom;
    /** @var Carbon */
    public $timeTo;
    /** @var int */
    public $placeId;
    /** @var int[] */
    public $speakerIds;

    /** @var string */
    public const DATE_FORMAT = 'Y-m-d';
    /** @var string */
    public const TIME_FORMAT = 'H:i:s';

    /**
     * @param  string  $date
     */
    public function setDate(string $date): void
    {
        $this->date = Carbon::createFromFormat(self::DATE_FORMAT, $date);
    }

    /**
     * @param  string  $date
     */
    public function setTimeFrom(string $time): void
    {
        $this->timeFrom = Carbon::createFromFormat(self::TIME_FORMAT, $time);
    }

    /**
     * @param  string  $date
     */
    public function setTimeTo(string $time): void
    {
        $this->timeTo = Carbon::createFromFormat(self::TIME_FORMAT, $time);
    }
}
