<?php

namespace App\Services\Leave;

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class LeaveBalanceEligibilityService
{
    public function __construct(
        private WorkingDayCalculator $workingDayCalculator
    ) {}

    /**
     * Check whether eligible allocations cover a leave request.
     *
     * This method never saves or deducts a leave balance.
     */
    public function check(
        Employee $employee,
        string $leaveTypeCode,
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

        $leaveType = LeaveType::query()
            ->where('code', $leaveTypeCode)
            ->where('is_active', true)
            ->first();

        if (! $leaveType) {
            return $this->failure(
                'The requested leave type is missing or inactive.'
            );
        }

        // LWP is governed by a service-year usage limit, not an allocation.
        if ($leaveType->code === 'LWP') {
            return $this->failure(
                'LWP eligibility must be checked against the service-year usage limit.'
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

        if ($isHalfDay && $workingDays !== 1) {
            return $this->failure(
                'A half-day request must contain exactly one working day.'
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
         * Load the employee's allocations for this leave type.
         *
         * We deliberately do not require period_start and period_end
         * to equal a service-year period. An allocation such as
         * carry-forward may have its own validity dates.
         */
        $balances = LeaveBalance::query()
            ->where('employee_id', $employee->id)
            ->where('leave_type_id', $leaveType->id)
            ->whereDate('period_start', '<=', $to->toDateString())
            ->whereDate('period_end', '>=', $from->toDateString())
            ->where('remaining_days', '>', 0)
            ->orderByRaw(
                'CASE WHEN expires_at IS NULL THEN 1 ELSE 0 END'
            )
            ->orderBy('expires_at')
            ->orderBy('id')
            ->get();

        /*
         * Simulated balances are kept separately from Eloquent models.
         * No database balance is modified by this check.
         */
        $availableDays = $balances->mapWithKeys(
            fn(LeaveBalance $balance) => [
                $balance->id => (int) round(
                    (float) $balance->remaining_days * 100
                ),
            ]
        );

        $remainingToCover = (int) round($requestedDays * 100);

        $date = $from->copy();

        while ($date->lte($to)) {
            $isWorkingDate = $this->workingDayCalculator->count(
                $date,
                $date
            ) === 1;

            if (! $isWorkingDate) {
                $date->addDay();

                continue;
            }

            $daysNeeded = $isHalfDay ? 50 : 100;

            /*
             * Only allocations whose validity dates cover this
             * particular working date can be used.
             */
            foreach ($balances as $balance) {
                if ($daysNeeded <= 0) {
                    break;
                }

                $periodStart = Carbon::parse(
                    $balance->period_start
                )->startOfDay();

                $periodEnd = Carbon::parse(
                    $balance->period_end
                )->startOfDay();

                if (
                    $date->lt($periodStart)
                    || $date->gt($periodEnd)
                ) {
                    continue;
                }

                if (
                    $balance->expires_at !== null
                    && $date->gt(
                        Carbon::parse($balance->expires_at)->startOfDay()
                    )
                ) {
                    continue;
                }

                $balanceId = $balance->id;
                $available = $availableDays->get($balanceId, 0);

                if ($available <= 0) {
                    continue;
                }

                $used = min($available, $daysNeeded);

                $availableDays->put(
                    $balanceId,
                    $available - $used
                );

                $daysNeeded -= $used;
                $remainingToCover -= $used;
            }

            if ($daysNeeded > 0) {
                return $this->failure(
                    'Insufficient leave balance for ' .
                        $date->toDateString() . '.'
                );
            }

            $date->addDay();
        }

        if ($remainingToCover > 0) {
            return $this->failure(
                'The available allocations do not cover the full request.'
            );
        }

        return [
            'eligible' => true,
            'message' => 'Sufficient leave balance is available.',
            'requested_days' => number_format(
                $requestedDays,
                2,
                '.',
                ''
            ),
        ];
    }

    private function failure(string $message): array
    {
        return [
            'eligible' => false,
            'message' => $message,
        ];
    }
}
