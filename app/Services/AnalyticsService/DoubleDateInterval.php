<?php

namespace App\Services\AnalyticsService;

use Carbon\Carbon;
use Carbon\CarbonInterval;
use Exception;

class DoubleDateInterval
{
    public Carbon $previousStart;
    public Carbon $previousEnd;
    public Carbon $start;
    public Carbon $end;

    const TYPE_YEAR = 'year';
    const TYPE_MONTH = 'month';

    /** @throws Exception */
    public function __construct(string $start, string $end, $interval = self::TYPE_YEAR)
    {
        $this->start = Carbon::createFromFormat('Y-m-d', $start)->startOfDay();
        $this->end = Carbon::createFromFormat('Y-m-d', $end)->endOfDay();
        $this->previousEnd = $this->start->clone()->endOfDay();
        $diff = $interval === self::TYPE_YEAR ? new CarbonInterval(1) : new CarbonInterval(0, 1);
        $this->previousStart = $this->start->clone()->sub($diff)->startOfDay();
    }

    /**
     * @return array<string, string>
     */
    public function previousPeriod(): array
    {
        return [$this->previousStart->format('Y-m-d'), $this->previousEnd->format('Y-m-d')];
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
