<?php

namespace App\Services\Leave;

use App\Models\LeaveApplication;
use App\Models\LeaveBalance;
use App\Models\LeaveBalanceTransaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class LeaveBalanceDeductionService
{
    public function __construct(
        private WorkingDayCalculator $workingDayCalculator
    ) {}

    /**
     * Deduct an approved leave application's days from valid allocations.
     *
     * The caller should invoke this service inside the same database
     * transaction that approves the leave application.
     *
     * @return array<int, array{
     *     balance_id: int,
     *     deducted_days: string,
     *     remaining_days: string
     * }>
     */
    public function deduct(
        LeaveApplication $application,
        User $actor
    ): array {
        return DB::transaction(function () use ($application, $actor) {
            /*
             * Reload and lock the application.
             *
             * This prevents concurrent approval attempts from
             * processing the same application simultaneously.
             */
            $lockedApplication = LeaveApplication::query()
                ->whereKey($application->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedApplication->status !== 'approved') {
                throw ValidationException::withMessages([
                    'application' => [
                        'Only approved leave applications can be deducted.',
                    ],
                ]);
            }
            $existingDeduction = LeaveBalanceTransaction::query()
                ->where('leave_application_id', $lockedApplication->id)
                ->where('transaction_type', 'deduction')
                ->exists();

            if ($existingDeduction) {
                throw ValidationException::withMessages([
                    'application' => [
                        'This leave application has already had its balance deducted.',
                    ],
                ]);
            }

            $employee = $lockedApplication->employee()->firstOrFail();

            if (! $employee->is_active) {
                throw ValidationException::withMessages([
                    'application' => [
                        'Inactive employees cannot have leave approved.',
                    ],
                ]);
            }

            $leaveType = $lockedApplication->leaveType()->firstOrFail();

            if ($leaveType->code === 'LWP') {
                throw ValidationException::withMessages([
                    'application' => [
                        'LWP must be processed through its service-year eligibility workflow.',
                    ],
                ]);
            }

            if (! $leaveType->is_active) {
                throw ValidationException::withMessages([
                    'application' => [
                        'The requested leave type is inactive.',
                    ],
                ]);
            }

            $from = Carbon::parse(
                $lockedApplication->leave_from_date
            )->startOfDay();

            $to = Carbon::parse(
                $lockedApplication->leave_to_date
            )->startOfDay();

            $requestedHundredths = (int) round(
                (float) $lockedApplication->leavedeductcount * 100
            );

            if ($to->lt($from) || $requestedHundredths <= 0) {
                throw ValidationException::withMessages([
                    'application' => [
                        'The leave application dates or requested days are invalid.',
                    ],
                ]);
            }

            /*
             * Half-day requests must cover exactly one working day.
             * Full and multiple-day requests must match the working-day
             * count, excluding weekends and configured holidays.
             */
            $duration = $lockedApplication->duration()->firstOrFail();

            if (! in_array(
                $duration->name,
                ['Full Day', 'Half Day', 'Multiple Days'],
                true
            )) {
                throw ValidationException::withMessages([
                    'application' => [
                        'The selected duration is not supported.',
                    ],
                ]);
            }

            $workingDays = $this->workingDayCalculator->count(
                $from,
                $to
            );

            $isHalfDay = $duration->name === 'Half Day';

            if (
                ($isHalfDay && $requestedHundredths !== 50)
                || (! $isHalfDay && $requestedHundredths % 100 !== 0)
            ) {
                throw ValidationException::withMessages([
                    'application' => [
                        'The requested days do not match the selected duration.',
                    ],
                ]);
            }
            if (
                $duration->name === 'Full Day'
                && ! $from->isSameDay($to)
            ) {
                throw ValidationException::withMessages([
                    'application' => [
                        'A Full Day request must cover exactly one date.',
                    ],
                ]);
            }

            if ($workingDays === 0) {
                throw ValidationException::withMessages([
                    'application' => [
                        'The requested period contains no working days.',
                    ],
                ]);
            }

            if (
                ($isHalfDay && $workingDays !== 1)
                || (! $isHalfDay && $requestedHundredths !== $workingDays * 100)
            ) {
                throw ValidationException::withMessages([
                    'application' => [
                        'The requested days do not match the working-day count.',
                    ],
                ]);
            }

            /*
             * Lock every potentially relevant allocation.
             *
             * Rows are ordered by expiry so allocations expiring sooner
             * are consumed first. Allocations without an expiry date
             * are considered last.
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
                ->lockForUpdate()
                ->get();

            /*
             * Track remaining amounts in integer hundredths.
             * For example, 1.50 days is represented as 150.
             */
            $available = [];

            foreach ($balances as $balance) {
                $available[$balance->id] = (int) round(
                    (float) $balance->remaining_days * 100
                );
            }

            /*
             * Track how much of each allocation this application uses.
             * We will create one ledger transaction per allocation.
             */
            $deductions = [];
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

                foreach ($balances as $balance) {
                    if ($daysNeeded === 0) {
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
                    $remaining = $available[$balanceId] ?? 0;

                    if ($remaining <= 0) {
                        continue;
                    }

                    $used = min($remaining, $daysNeeded);

                    $available[$balanceId] -= $used;
                    $daysNeeded -= $used;

                    $deductions[$balanceId] =
                        ($deductions[$balanceId] ?? 0) + $used;
                }

                if ($daysNeeded > 0) {
                    throw ValidationException::withMessages([
                        'application' => [
                            'Insufficient eligible leave balance for ' .
                                $date->toDateString() . '.',
                        ],
                    ]);
                }

                $date->addDay();
            }

            $totalDeducted = array_sum($deductions);

            if ($totalDeducted !== $requestedHundredths) {
                throw new RuntimeException(
                    'The calculated deductions do not match the requested leave days.'
                );
            }

            $results = [];

            foreach ($deductions as $balanceId => $deductedHundredths) {
                $balance = $balances->firstWhere('id', $balanceId);

                if (! $balance) {
                    throw new RuntimeException(
                        'A deduction references an allocation that was not loaded.'
                    );
                }

                $remainingHundredths =
                    (int) round((float) $balance->remaining_days * 100)
                    - $deductedHundredths;

                if ($remainingHundredths < 0) {
                    throw new RuntimeException(
                        'A leave allocation cannot have a negative balance.'
                    );
                }

                $balance->remaining_days = number_format(
                    $remainingHundredths / 100,
                    2,
                    '.',
                    ''
                );

                $balance->save();

                LeaveBalanceTransaction::create([
                    'leave_balance_id' => $balance->id,
                    'leave_application_id' => $lockedApplication->id,
                    'reverses_transaction_id' => null,
                    'transaction_type' => 'deduction',
                    'days' => number_format(
                        $deductedHundredths / 100,
                        2,
                        '.',
                        ''
                    ),
                    'balance_after' => $balance->remaining_days,
                    'description' => 'Leave approved and balance deducted.',
                    'created_by' => $actor->id,
                ]);

                $results[] = [
                    'balance_id' => $balance->id,
                    'deducted_days' => number_format(
                        $deductedHundredths / 100,
                        2,
                        '.',
                        ''
                    ),
                    'remaining_days' => $balance->remaining_days,
                ];
            }

            return $results;
        });
    }
}
