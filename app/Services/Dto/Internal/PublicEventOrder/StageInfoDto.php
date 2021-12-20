<?php

namespace App\Services\Dto\Internal\PublicEventOrder;

use Illuminate\Support\Carbon;
use Jenssegers\Date\Date;

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
    public $dateFrom;
    /** @var Carbon */
    public $dateTo;
    /** @var Carbon */
    public $timeFrom;
    /** @var Carbon */
    public $timeTo;
    /** @var int */
    public $placeId;
    /** @var int[] */
    public $speakerIds;

    public const DATE_FORMAT = 'Y-m-d';

    public const TIME_FORMAT = 'H:i:s';

    public function setDateFrom(string $dateFrom): void
    {
        $this->dateFrom = Carbon::createFromFormat(self::DATE_FORMAT, $dateFrom);
    }

    public function setDateTo(string $dateTo): void
    {
        $this->dateTo = Carbon::createFromFormat(self::DATE_FORMAT, $dateTo);
    }

    public function getDateFormatted(): string
    {
        $formatDate = static fn(Carbon $date) => Date::parse($date)->format('j F') . ' ' . short_day_of_week($date->dayOfWeek);

        return $formatDate($this->dateFrom) . ($this->dateFrom != $this->dateTo ? ' - ' . $formatDate($this->dateTo) : '');
    }

    /**
     * @param string $date
     */
    public function setTimeFrom(string $time): void
    {
        $this->timeFrom = Carbon::createFromFormat(self::TIME_FORMAT, $time);
    }

    /**
     * @param string $date
     */
    public function setTimeTo(string $time): void
    {
        $this->timeTo = Carbon::createFromFormat(self::TIME_FORMAT, $time);
    }
}
