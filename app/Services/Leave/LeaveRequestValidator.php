<?php

namespace App\Services\Leave;

use App\Models\Duration;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Throwable;

class LeaveRequestValidator
{
    public function __construct(
        private WorkingDayCalculator $workingDayCalculator
    ) {}

    /**
     * Validate a leave request and calculate its deduction.
     *
     * This method does not save an application or change a balance.
     */
    public function validate(
        string $fromDate,
        string $toDate,
        int $durationId,
        ?string $halfDayPeriod = null
    ): array {
        // Validate and normalize the supplied dates.
        $from = $this->parseDate($fromDate, 'leave_from_date');
        $to = $this->parseDate($toDate, 'leave_to_date');

        if ($to->lt($from)) {
            throw ValidationException::withMessages([
                'leave_to_date' => 'The end date must be on or after the start date.',
            ]);
        }

        // Load the duration from the database.
        $duration = Duration::query()->find($durationId);

        if (! $duration) {
            throw ValidationException::withMessages([
                'duration_id' => 'The selected duration is invalid.',
            ]);
        }

        // Ensure the duration is one of the supported options.
        if (! in_array($duration->name, [
            'Full Day',
            'Half Day',
            'Multiple Days',
        ], true)) {
            throw ValidationException::withMessages([
                'duration_id' => 'The selected duration is not supported.',
            ]);
        }

        // Half-day leave must cover one date and specify a half.
        if ($duration->name === 'Half Day') {
            if (! $from->isSameDay($to)) {
                throw ValidationException::withMessages([
                    'leave_to_date' => 'Half-day leave must cover exactly one date.',
                ]);
            }

            if (! in_array($halfDayPeriod, [
                'first_half',
                'second_half',
            ], true)) {
                throw ValidationException::withMessages([
                    'half_day_period' => 'Select either the first half or second half.',
                ]);
            }
        } elseif ($halfDayPeriod !== null) {
            throw ValidationException::withMessages([
                'half_day_period' => 'A half-day period is allowed only for half-day leave.',
            ]);
        }

        // Full-day duration represents exactly one calendar date.
        if (
            $duration->name === 'Full Day'
            && ! $from->isSameDay($to)
        ) {
            throw ValidationException::withMessages([
                'leave_to_date' => 'Full-day duration must cover exactly one date. Select Multiple Days for a date range.',
            ]);
        }

        // Count eligible weekdays, excluding weekends and public holidays.
        $workingDays = $this->workingDayCalculator->count($from, $to);

        // A request must contain at least one eligible working day.
        if ($workingDays === 0) {
            throw ValidationException::withMessages([
                'leave_from_date' => 'The selected date range contains no eligible working days.',
            ]);
        }

        // A half-day request deducts half a working day.
        $deductDays = $duration->name === 'Half Day'
            ? 0.50
            : (float) $workingDays;

        return [
            'from_date' => $from->toDateString(),
            'to_date' => $to->toDateString(),
            'duration_id' => $duration->id,
            'duration_name' => $duration->name,
            'half_day_period' => $halfDayPeriod,
            'working_days' => $workingDays,
            'deduct_days' => number_format($deductDays, 2, '.', ''),
        ];
    }

    /**
     * Parse a strict YYYY-MM-DD date.
     */
    private function parseDate(string $value, string $field): Carbon
    {
        try {
            $date = Carbon::createFromFormat('!Y-m-d', $value);

            if ($date && $date->format('Y-m-d') === $value) {
                return $date->startOfDay();
            }
        } catch (Throwable) {
            // Invalid input can cause Carbon to throw an exception.
        }

        throw ValidationException::withMessages([
            $field => 'Enter a valid date in YYYY-MM-DD format.',
        ]);
    }
}
