<?php

namespace App\Services\Leave;

use App\Models\Holiday;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use InvalidArgumentException;

class WorkingDayCalculator
{
    /**
     * Count working days in an inclusive date range.
     *
     * Saturdays, Sundays, and configured public holidays are excluded.
     */
    public function count(
        CarbonInterface|string $fromDate,
        CarbonInterface|string $toDate
    ): int {
        $from = Carbon::parse($fromDate)->startOfDay();
        $to = Carbon::parse($toDate)->startOfDay();

        if ($to->lt($from)) {
            throw new InvalidArgumentException(
                'The end date must be on or after the start date.'
            );
        }

        // Fetch holidays once rather than querying the database for every date.
        $holidays = Holiday::query()
            ->whereDate('holiday_date', '>=', $from->toDateString())
            ->whereDate('holiday_date', '<=', $to->toDateString())
            ->pluck('holiday_date')
            ->map(fn($date) => Carbon::parse($date)->toDateString())
            ->flip();

        $workingDays = 0;

        // Include both the start date and the end date.
        $date = $from->copy();

        while ($date->lte($to)) {
            $isWeekend = $date->isSaturday() || $date->isSunday();

            $isHoliday = $holidays->has($date->toDateString());

            if (! $isWeekend && ! $isHoliday) {
                $workingDays++;
            }

            $date->addDay();
        }

        return $workingDays;
    }
}
