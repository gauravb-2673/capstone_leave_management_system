<?php

namespace App\Services\Leave;

use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveType;
use App\Support\ServiceYear;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class LwpEligibilityService
{
    private const ANNUAL_LIMIT = 12.00;

    public function __construct(
        private WorkingDayCalculator $workingDayCalculator
    ) {}

    /**
     * Check whether an employee can take the requested LWP.
     *
     * This method does not save or modify any database records.
     */
    public function check(
        Employee $employee,
        CarbonInterface|string $fromDate,
        CarbonInterface|string $toDate,
        float $requestedDays
    ): array {
        $from = Carbon::parse($fromDate)->startOfDay();
        $to = Carbon::parse($toDate)->startOfDay();

        if ($to->lt($from)) {
            return $this->failure(
                'The end date must be on or after the start date.'
            );
        }

        if ($requestedDays <= 0) {
            return $this->failure(
                'Requested leave days must be greater than zero.'
            );
        }

        if (! $employee->is_active) {
            return $this->failure(
                'Inactive employees cannot request leave.'
            );
        }

        $joiningDate = Carbon::parse(
            $employee->joining_date
        )->startOfDay();

        if ($from->lt($joiningDate)) {
            return $this->failure(
                'Leave cannot start before the employee joining date.'
            );
        }

        $lwpType = LeaveType::query()
            ->where('code', 'LWP')
            ->where('is_active', true)
            ->first();

        if (! $lwpType) {
            return $this->failure(
                'The LWP leave type is missing or inactive.'
            );
        }

        $workingDays = $this->workingDayCalculator->count(
            $from,
            $to
        );

        if ($workingDays === 0) {
            return $this->failure(
                'The requested period contains no working days.'
            );
        }

        $isHalfDay = abs($requestedDays - 0.50) < 0.000001;

        if ($isHalfDay && ! $from->isSameDay($to)) {
            return $this->failure(
                'A half-day request must cover exactly one date.'
            );
        }

        if (
            ! $isHalfDay
            && abs($requestedDays - $workingDays) >= 0.000001
        ) {
            return $this->failure(
                'Requested leave days do not match the working-day count.'
            );
        }

        /*
         * Check each service year separately.
         *
         * A request spanning an employee's anniversary must not
         * use the annual allowance from one year to cover another.
         */
        $date = $from->copy();

        while ($date->lte($to)) {
            $serviceYearStart = ServiceYear::startDate(
                $joiningDate,
                $date
            );

            $serviceYearEnd = ServiceYear::endDate(
                $joiningDate,
                $date
            );

            $segmentStart = $date->copy();

            $segmentEnd = $to->copy()->min($serviceYearEnd);

            $requestedForYear = $this->workingDayCalculator->count(
                $segmentStart,
                $segmentEnd
            );

            /*
             * A half-day request is for one date, so its entire
             * half-day usage belongs to that date's service year.
             */
            if ($isHalfDay) {
                $requestedForYear = 0.50;
            }

            $usedDays = $this->approvedLwpUsage(
                $employee,
                $lwpType->id,
                $serviceYearStart,
                $serviceYearEnd
            );

            $totalAfterRequest = $usedDays + $requestedForYear;

            if ($totalAfterRequest > self::ANNUAL_LIMIT) {
                return [
                    'eligible' => false,
                    'message' => sprintf(
                        'The LWP limit of 12 days would be exceeded for service year %s to %s.',
                        $serviceYearStart->toDateString(),
                        $serviceYearEnd->toDateString()
                    ),
                    'service_year_start' => $serviceYearStart->toDateString(),
                    'service_year_end' => $serviceYearEnd->toDateString(),
                    'used_days' => number_format($usedDays, 2, '.', ''),
                    'requested_days' => number_format(
                        $requestedForYear,
                        2,
                        '.',
                        ''
                    ),
                    'annual_limit' => number_format(
                        self::ANNUAL_LIMIT,
                        2,
                        '.',
                        ''
                    ),
                ];
            }

            /*
             * Move to the day after this service-year segment.
             */
            $date = $serviceYearEnd->copy()->addDay();
        }

        return [
            'eligible' => true,
            'message' => 'The requested LWP is within the service-year limit.',
            'requested_days' => number_format(
                $requestedDays,
                2,
                '.',
                ''
            ),
            'annual_limit' => number_format(
                self::ANNUAL_LIMIT,
                2,
                '.',
                ''
            ),
        ];
    }

    /**
     * Calculate approved LWP usage within one service year.
     */
    private function approvedLwpUsage(
        Employee $employee,
        int $lwpTypeId,
        CarbonInterface $serviceYearStart,
        CarbonInterface $serviceYearEnd
    ): float {
        $applications = LeaveApplication::query()
            ->where('employee_id', $employee->id)
            ->where('leave_type_id', $lwpTypeId)
            ->where('status', 'approved')
            ->whereDate(
                'leave_from_date',
                '<=',
                $serviceYearEnd->toDateString()
            )
            ->whereDate(
                'leave_to_date',
                '>=',
                $serviceYearStart->toDateString()
            )
            ->lockForUpdate()
            ->get();

        $usedDays = 0.0;

        foreach ($applications as $application) {
            $applicationStart = Carbon::parse(
                $application->leave_from_date
            )->startOfDay();

            $applicationEnd = Carbon::parse(
                $application->leave_to_date
            )->startOfDay();

            /*
             * Find the portion of this application that overlaps
             * the service year being checked.
             */
            $overlapStart = $applicationStart->max(
                Carbon::parse($serviceYearStart)->startOfDay()
            );

            $overlapEnd = $applicationEnd->min(
                Carbon::parse($serviceYearEnd)->startOfDay()
            );

            if ($overlapEnd->lt($overlapStart)) {
                continue;
            }

            /*
             * Half-day applications can only cover one date.
             * Count their actual approved deduction as 0.50.
             */
            if (
                abs((float) $application->leavedeductcount - 0.50)
                < 0.000001
            ) {
                $usedDays += 0.50;

                continue;
            }

            /*
             * For a multi-day application, count only the working
             * days falling inside this service-year overlap.
             */
            $usedDays += $this->workingDayCalculator->count(
                $overlapStart,
                $overlapEnd
            );
        }

        return $usedDays;
    }

    private function failure(string $message): array
    {
        return [
            'eligible' => false,
            'message' => $message,
        ];
    }
}
