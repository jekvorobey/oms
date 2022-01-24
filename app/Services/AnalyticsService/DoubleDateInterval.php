<?php

namespace App\Services\AnalyticsService;

use Carbon\Carbon;
use Carbon\CarbonInterval;
use Exception;

class DoubleDateInterval
{
    private const START_TIME = [0, 0];
    private const END_TIME = [23, 59, 59, 999999];
    public Carbon $previousStart;
    public Carbon $start;
    public Carbon $end;
    public CarbonInterval $diff;

    const TYPE_YEAR = 'year';
    const TYPE_MONTH = 'month';

    /** @throws Exception */
    public function __construct(string $start, string $end, $interval = self::TYPE_YEAR)
    {
        $this->start = Carbon::createFromFormat('Y-m-d', $start)->setTime(...self::START_TIME);
        $this->end = Carbon::createFromFormat('Y-m-d', $end)->setTime(...self::END_TIME);
        $this->diff = $interval === self::TYPE_YEAR ? new CarbonInterval(1) : new CarbonInterval(0, 1);
        $this->previousStart = $this->start->clone()->sub($this->diff)->setTime(...self::END_TIME);
    }

    /**
     * @return array<string, string>
     */
    public function previousPeriod(): array
    {
        return [$this->previousStart->format('Y-m-d'), $this->start->format('Y-m-d')];
    }

    /**
     * @return array<string, string>
     */
    public function currentPeriod(): array
    {
        return [$this->start->format('Y-m-d'), $this->end->format('Y-m-d')];
    }

    /**
     * @return array<string, string>
     */
    public function fullPeriod(): array
    {
        return [$this->previousStart->format('Y-m-d'), $this->end->format('Y-m-d')];
    }

    public function isDateWithinPreviousPeriod(Carbon $date): bool
    {
        return $date
            ->between(
                $this->previousStart,
                $this->start
            );
    }

    public function isDateWithinCurrentPeriod(Carbon $date): bool
    {
        return $date
            ->between(
                $this->start,
                $this->end
            );
    }

}
