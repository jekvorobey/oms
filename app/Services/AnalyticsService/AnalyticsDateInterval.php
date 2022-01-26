<?php

namespace App\Services\AnalyticsService;

use Carbon\Carbon;
use Carbon\CarbonInterval;
use Exception;

class AnalyticsDateInterval
{
    public Carbon $previousStart;
    public Carbon $previousEnd;
    public Carbon $start;
    public Carbon $end;

    const TYPE_YEAR = 'year';
    const TYPE_MONTH = 'month';
    const TYPE_DAY = 'day';

    const TYPES = [
      self::TYPE_YEAR => [
          'groupBy' => self::TYPE_MONTH
      ],
      self::TYPE_MONTH => [
          'groupBy' => self::TYPE_DAY
      ]
    ];

    /** @throws Exception */
    public function __construct(string $start, string $end, $interval = self::TYPE_YEAR)
    {
        $intervalScaleUnit = self::TYPES[$interval]['groupBy'];
        $this->start = Carbon::createFromFormat('Y-m-d', $start)->startOfDay();
        $this->end = Carbon::createFromFormat('Y-m-d', $end)->endOfDay();
        $this->previousEnd = $this->start->clone()->sub(1, $intervalScaleUnit)->endOfDay();
        $this->previousStart = $this->start->clone()->sub(1, $intervalScaleUnit)->startOfDay();
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
                $this->previousEnd
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
