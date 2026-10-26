<?php

namespace App\Services\Leave;

use App\Models\Duration;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class LeaveRequestValidator
{
    /**
     * Validate the dates and duration of a leave request.
     *
     * This method validates the request structure only.
     * Working-day calculations and balance checks happen separately.
     */
    public function validate(
        string $fromDate,
        string $toDate,
        int $durationId,
        ?string $halfDayPeriod = null
    ): array {
        // Parse dates strictly so invalid calendar dates are rejected.
        $from = $this->parseDate($fromDate, 'leave_from_date');
        $to = $this->parseDate($toDate, 'leave_to_date');

        if ($to->lt($from)) {
            throw ValidationException::withMessages([
                'leave_to_date' => 'The end date must be on or after the start date.',
            ]);
        }

        // Load the duration from the database instead of assuming its ID.
        $duration = Duration::query()->find($durationId);

        if (! $duration) {
            throw ValidationException::withMessages([
                'duration_id' => 'The selected duration is invalid.',
            ]);
        }

        if ($duration->name === 'Half Day') {
            if (! $from->isSameDay($to)) {
                throw ValidationException::withMessages([
                    'leave_to_date' => 'Half-day leave must cover exactly one date.',
                ]);
            }

            if (! in_array($halfDayPeriod, ['first_half', 'second_half'], true)) {
                throw ValidationException::withMessages([
                    'half_day_period' => 'Select either the first half or second half.',
                ]);
            }
        } elseif ($halfDayPeriod !== null) {
            throw ValidationException::withMessages([
                'half_day_period' => 'A half-day period is allowed only for half-day leave.',
            ]);
        }

        if ($duration->name === 'Full Day' && ! $from->isSameDay($to)) {
            throw ValidationException::withMessages([
                'leave_to_date' => 'Full-day duration must cover exactly one date. Select Multiple Days for a date range.',
            ]);
        }

        if (! in_array($duration->name, ['Full Day', 'Half Day', 'Multiple Days'], true)) {
            throw ValidationException::withMessages([
                'duration_id' => 'The selected duration is not supported.',
            ]);
        }

        return [
            'from_date' => $from->toDateString(),
            'to_date' => $to->toDateString(),
            'duration_id' => $duration->id,
            'duration_name' => $duration->name,
            'half_day_period' => $halfDayPeriod,
        ];
    }

    /**
     * Parse a date in YYYY-MM-DD format and reject invalid dates.
     */
    private function parseDate(string $value, string $field): Carbon
    {
        $date = Carbon::createFromFormat('!Y-m-d', $value);

        if (! $date || $date->format('Y-m-d') !== $value) {
            throw ValidationException::withMessages([
                $field => 'Enter a valid date in YYYY-MM-DD format.',
            ]);
        }

        return $date->startOfDay();
    }
}
