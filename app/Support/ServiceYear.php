<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use InvalidArgumentException;

class ServiceYear
{
    /**
     * Return the start date of the service year containing $date.
     *
     * Example:
     * Joining date: 2025-01-15
     * Date:         2026-06-10
     * Result:       2026-01-15
     */
    public static function startDate(
        CarbonInterface|string $joiningDate,
        CarbonInterface|string $date
    ): Carbon {
        $joining = Carbon::parse($joiningDate)->startOfDay();
        $current = Carbon::parse($date)->startOfDay();

        if ($current->lt($joining)) { //lt() means "less than."
            throw new InvalidArgumentException(
                'The calculation date cannot be before the joining date.'
            );
        }

        $yearsCompleted = $joining->diffInYears($current); // Calculate the years between the dates

        $start = $joining->copy()->addYears( //eparate copy of the joining-date object so that date modifications do not change the original object. //Adds one year.
            (int) $yearsCompleted
        );

        // Protect against an anniversary that has not yet occurred
        // in the current calendar year.
        if ($start->gt($current)) { //gt() means "greater than."
            $start->subYear(); //If it does, the method subtracts a year from the candidate.
        }

        return $start;
    }

    /**
     * Return the end date of a service year.
     *
     * The period ends one day before the next anniversary.
     */
    public static function endDate(
        CarbonInterface|string $joiningDate,
        CarbonInterface|string $date
    ): Carbon {
        return self::startDate($joiningDate, $date)
            ->copy()
            ->addYear()
            ->subDay();
    }

    /**
     * Return the number of completed service anniversaries.
     */
    public static function completedYears(
        CarbonInterface|string $joiningDate,
        CarbonInterface|string $date
    ): int {
        $joining = Carbon::parse($joiningDate)->startOfDay();
        $current = Carbon::parse($date)->startOfDay();

        if ($current->lt($joining)) {
            throw new InvalidArgumentException(
                'The calculation date cannot be before the joining date.'
            );
        }

        return (int) $joining->diffInYears($current);
    }
}
