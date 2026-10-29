<?php

namespace App\Services\Leave;

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveBalanceTransaction;
use App\Models\LeaveType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use App\Models\LeaveApplication;
use App\Models\LeaveAllocationDeletionAudit;

class SpecialLeaveAllocationService
{
    /**
     * Allocate maternity or paternity leave to an employee.
     *
     * The grant key makes a repeated identical submission idempotent.
     * A different submission cannot reuse an existing grant key.
     */
    public function allocate(
        Employee $employee,
        LeaveType $leaveType,
        string $days,
        string $validFrom,
        string $validUntil,
        string $reason,
        string $grantKey,
        User $admin
    ): LeaveBalance {
        return DB::transaction(function () use (
            $employee,
            $leaveType,
            $days,
            $validFrom,
            $validUntil,
            $reason,
            $grantKey,
            $admin
        ) {
            // Lock the employee row to serialize allocation requests
            // for this employee.
            $lockedEmployee = Employee::query()
                ->whereKey($employee->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedEmployee->is_active) {
                throw new RuntimeException(
                    'An inactive employee cannot receive a new allocation.'
                );
            }

            // Reload the leave type and verify it is eligible.
            $lockedLeaveType = LeaveType::query()
                ->whereKey($leaveType->id)
                ->whereIn('code', ['MT', 'PT'])
                ->where('is_active', true)
                ->first();

            if (! $lockedLeaveType) {
                throw new RuntimeException(
                    'Only active Maternity Leave (MT) and Paternity Leave (PT) can be allocated here.'
                );
            }

            // Normalize the amount to two decimal places.
            $amount = number_format((float) $days, 2, '.', '');

            if ((float) $amount <= 0) {
                throw new RuntimeException(
                    'The allocation amount must be greater than zero.'
                );
            }

            // Calculate the number of calendar dates in the inclusive period.
            $startDate = Carbon::parse($validFrom)->startOfDay();
            $endDate = Carbon::parse($validUntil)->startOfDay();

            // Reject allocations whose validity has already ended.
            if ($endDate->lt(today())) {
                throw new RuntimeException(
                    'The Valid Until date cannot be in the past.'
                );
            }

            // Ensure the end date is not before the start date.
            if ($endDate->lt($startDate)) {
                throw new RuntimeException(
                    'The Valid Until date cannot be before the Valid From date.'
                );
            }

            // Count both the start date and end date.
            $calendarDays = (int) $startDate->diffInDays($endDate) + 1;

            // Require sufficient calendar days for the allocated amount.
            $minimumCalendarDays = (int) ceil((float) $amount);

            if ($calendarDays < $minimumCalendarDays) {
                throw new RuntimeException(
                    "The validity period must cover at least {$minimumCalendarDays} calendar days for this allocation amount."
                );
            }

            /*
             * Check the grant key before checking overlapping allocations.
             * A repeated identical submission returns the existing record.
             */
            $existingBalance = LeaveBalance::query()
                ->where('grant_key', $grantKey)
                ->first();

            if ($existingBalance) {
                $sameDetails =
                    (int) $existingBalance->employee_id === (int) $lockedEmployee->id
                    && (int) $existingBalance->leave_type_id === (int) $lockedLeaveType->id
                    && $existingBalance->allocation_type === 'special'
                    && number_format((float) $existingBalance->allocated_days, 2, '.', '') === $amount
                    && $existingBalance->period_start->toDateString() === $startDate->toDateString()
                    && $existingBalance->period_end->toDateString() === $endDate->toDateString();

                if (! $sameDetails) {
                    throw new RuntimeException(
                        'This grant key has already been used for a different allocation.'
                    );
                }

                return $existingBalance;
            }

            /*
             * Date ranges overlap when:
             * new start <= existing end AND new end >= existing start.
             *
             * The leave type condition means MT and PT may overlap each other,
             * but two MT allocations or two PT allocations may not overlap.
             */
            $hasOverlap = LeaveBalance::query()
                ->where('employee_id', $lockedEmployee->id)
                ->where('leave_type_id', $lockedLeaveType->id)
                ->where('allocation_type', 'special')
                ->whereDate('period_start', '<=', $endDate->toDateString())
                ->whereDate('period_end', '>=', $startDate->toDateString())
                ->lockForUpdate()
                ->exists();

            if ($hasOverlap) {
                throw new RuntimeException(
                    "This employee already has an {$lockedLeaveType->code} allocation that overlaps the selected validity period."
                );
            }

            // Create a separate balance record for this special allocation.
            $balance = LeaveBalance::create([
                'employee_id' => $lockedEmployee->id,
                'leave_type_id' => $lockedLeaveType->id,
                'allocation_type' => 'special',
                'allocated_days' => $amount,
                'remaining_days' => $amount,
                'period_start' => $startDate->toDateString(),
                'period_end' => $endDate->toDateString(),
                'expires_at' => $endDate->toDateString(),
                'source_balance_id' => null,
                'grant_key' => $grantKey,
            ]);

            // Record the allocation in the ledger.
            LeaveBalanceTransaction::create([
                'leave_balance_id' => $balance->id,
                'leave_application_id' => null,
                'reverses_transaction_id' => null,
                'transaction_type' => 'allocation',
                'days' => $amount,
                'balance_after' => $amount,
                'description' => sprintf(
                    'Special %s allocation. Valid from %s to %s. Reason: %s',
                    $lockedLeaveType->code,
                    $startDate->toDateString(),
                    $endDate->toDateString(),
                    $reason
                ),
                'created_by' => $admin->id,
            ]);

            return $balance;
        });
    }

    /**
     * Delete an unused, unexpired special leave allocation.
     *
     * A historical audit snapshot is preserved before deleting the
     * allocation and its ledger transactions.
     */
    public function delete(
        int $balanceId,
        string $reason,
        User $actor
    ): void {
        DB::transaction(function () use ($balanceId, $reason, $actor) {
            $balance = LeaveBalance::query()
                ->whereKey($balanceId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($balance->allocation_type !== 'special') {
                throw new RuntimeException(
                    'Only special leave allocations can be deleted here.'
                );
            }

            $employee = Employee::query()
                ->whereKey($balance->employee_id)
                ->lockForUpdate()
                ->firstOrFail();

            $leaveType = LeaveType::query()
                ->whereKey($balance->leave_type_id)
                ->firstOrFail();

            if (! in_array($leaveType->code, ['MT', 'PT'], true)) {
                throw new RuntimeException(
                    'Only Maternity Leave (MT) and Paternity Leave (PT) allocations can be deleted here.'
                );
            }

            /*
         * The validity end date is inclusive. An allocation remains
         * valid throughout Valid Until and expires the following day.
         */
            if (
                $balance->expires_at !== null
                && $balance->expires_at->lt(today())
            ) {
                throw new RuntimeException(
                    'Expired allocations cannot be deleted.'
                );
            }

            /*
         * Only a completely unused allocation can be deleted.
         * Decimal values are compared as normalized strings.
         */
            if (
                number_format((float) $balance->allocated_days, 2, '.', '')
                !== number_format((float) $balance->remaining_days, 2, '.', '')
            ) {
                throw new RuntimeException(
                    'An allocation that has been partially or fully used cannot be deleted.'
                );
            }

            /*
         * Applications in every status count: pending, approved,
         * rejected, or cancelled. Match employee, leave type, and
         * overlapping dates because applications do not directly
         * reference a particular allocation record.
         */
            $hasApplication = LeaveApplication::query()
                ->where('employee_id', $balance->employee_id)
                ->where('leave_type_id', $balance->leave_type_id)
                ->whereDate('leave_from_date', '<=', $balance->period_end)
                ->whereDate('leave_to_date', '>=', $balance->period_start)
                ->exists();

            if ($hasApplication) {
                throw new RuntimeException(
                    'This allocation cannot be deleted because a leave application exists for the same employee, leave type, and validity period.'
                );
            }

            /*
         * Load the related records needed for the permanent audit
         * snapshot before deleting anything.
         */
            $employee->loadMissing('user');

            $transactions = LeaveBalanceTransaction::query()
                ->where('leave_balance_id', $balance->id)
                ->lockForUpdate()
                ->get();

            $transactionIds = $transactions->modelKeys();

            /*
         * Never delete a transaction that another ledger transaction
         * references through reverses_transaction_id.
         */
            if ($transactionIds !== []) {
                $hasReversalReference = LeaveBalanceTransaction::query()
                    ->whereIn('reverses_transaction_id', $transactionIds)
                    ->exists();

                if ($hasReversalReference) {
                    throw new RuntimeException(
                        'This allocation has linked ledger history and cannot be deleted safely.'
                    );
                }
            }

            /*
         * Keep the audit snapshot and hard deletion atomic.
         * If any later operation fails, the snapshot also rolls back.
         */
            LeaveAllocationDeletionAudit::create([
                'original_balance_id' => $balance->id,
                'employee_id' => $employee->id,
                'employee_code' => $employee->employee_code,
                'employee_name' => $employee->user?->name,
                'leave_type_id' => $leaveType->id,
                'leave_type_code' => $leaveType->code,
                'leave_type_name' => $leaveType->name,
                'grant_key' => $balance->grant_key,
                'allocated_days' => $balance->allocated_days,
                'remaining_days' => $balance->remaining_days,
                'valid_from' => $balance->period_start->toDateString(),
                'valid_until' => $balance->period_end->toDateString(),
                'deletion_reason' => trim($reason),
                'deleted_by' => $actor->id,
                'deleted_at' => now(),
            ]);

            /*
         * The audit snapshot now preserves the allocation history.
         * Delete its ledger transactions before deleting the balance,
         * because the database foreign key uses restrictOnDelete().
         */
            LeaveBalanceTransaction::query()
                ->where('leave_balance_id', $balance->id)
                ->delete();

            $balance->delete();
        });
    }
}
