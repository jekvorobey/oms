<?php

namespace App\Services\AnalyticsService;

use Carbon\Carbon;
use Exception;

class AnalyticsDateInterval
{
    public Carbon $previousStart;
    public Carbon $previousEnd;
    public Carbon $start;
    public Carbon $end;

    /** @throws Exception */
    public function __construct(string $start, string $end)
    {
        $this->start = Carbon::createFromFormat('Y-m-d', $start)->startOfDay();
        $this->end = Carbon::createFromFormat('Y-m-d', $end)->endOfDay();

        $this->previousEnd = $this->start->copy()->subDay();
        $this->previousStart = $this->previousEnd->copy()->subDays($this->end->diffInDays($this->start));
    }

    public function currentPeriodDays(): int
    {
        return $this->start->diffInDays($this->end) + 1;
    }

    /**
     * @return array<string, string>
     */
    public function previousPeriod(): array
    {
        return [$this->previousStart->format('Y-m-d H:i:s'), $this->previousEnd->format('Y-m-d H:i:s')];
    }

    /**
     * @return array<string, string>
     */
    public function currentPeriod(): array
    {
        return [$this->start->format('Y-m-d H:i:s'), $this->end->format('Y-m-d H:i:s')];
    }

    /**
     * @return array<string, string>
     */
    public function fullPeriod(): array
    {
        return [$this->previousStart->format('Y-m-d H:i:s'), $this->end->format('Y-m-d H:i:s')];
    }

    public function isDateWithinPreviousPeriod(Carbon $date): bool
    {
        return $date
            ->between($this->previousStart, $this->previousEnd);
    }

    public function isDateWithinCurrentPeriod(Carbon $date): bool
    {
        return $date
            ->between($this->start, $this->end);
    }
}
