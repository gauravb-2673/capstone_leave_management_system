<?php

namespace App\Services\Leave;

use App\Models\Employee;
use App\Support\ServiceYear;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class LeaveEntitlementService
{
    /**
     * Calculate annual leave entitlements for an employee.
     *
     * This method calculates eligibility only.
     * It does not create or modify database records.
     */
    public function calculate(
        Employee $employee,
        CarbonInterface|string $date
    ): array {
        $date = Carbon::parse($date)->startOfDay();

        $joiningDate = Carbon::parse(
            $employee->joining_date
        )->startOfDay();

        // No entitlement can be calculated before joining.
        if ($date->lt($joiningDate)) {
            return [];
        }

        // This policy currently prevents grants while inactive.
        // Reactivation does not automatically grant missed allocations.
        if (! $employee->is_active) {
            return [];
        }

        $completedYears = ServiceYear::completedYears(
            $joiningDate,
            $date
        );

        $periodStart = ServiceYear::startDate(
            $joiningDate,
            $date
        );

        $periodEnd = ServiceYear::endDate(
            $joiningDate,
            $date
        );

        $entitlements = [];

        // CL: 12 days annually after completing one year.
        if ($completedYears >= 1) {
            $entitlements[] = [
                'code' => 'CL',
                'allocated_days' => 12.00,
                'allocation_type' => 'annual',
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'grant_key' => sprintf(
                    'employee:%d:CL:%s',
                    $employee->id,
                    $periodStart->toDateString()
                ),
            ];

            // SL: 6 days annually after completing one year.
            $entitlements[] = [
                'code' => 'SL',
                'allocated_days' => 6.00,
                'allocation_type' => 'annual',
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'grant_key' => sprintf(
                    'employee:%d:SL:%s',
                    $employee->id,
                    $periodStart->toDateString()
                ),
            ];
        }

        return $entitlements;
    }
}
