<?php

namespace App\Services\Leave;

use App\Models\LeaveApplication;
use App\Models\LeaveApplicationApproval;
use App\Models\LeaveBalance;
use App\Models\LeaveBalanceTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeaveCancellationService
{
    /**
     * Cancel a leave application according to the actor's role.
     *
     * Employees may cancel their own pending applications.
     * Admins may cancel approved applications.
     */
    public function cancel(
        LeaveApplication $leaveApplication,
        User $actor,
        string $reason
    ): LeaveApplication {
        return DB::transaction(function () use (
            $leaveApplication,
            $actor,
            $reason
        ) {
            $application = LeaveApplication::query()
                ->whereKey($leaveApplication->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $reason = trim($reason);

            if ($reason === '') {
                throw ValidationException::withMessages([
                    'cancellation_reason' => 'A cancellation reason is required.',
                ]);
            }

            if ($actor->role === 'employee') {
                $this->cancelPendingApplication($application, $actor, $reason);
            } elseif ($actor->role === 'admin') {
                $this->cancelApprovedApplication($application, $actor, $reason);
            } else {
                throw ValidationException::withMessages([
                    'role' => 'You are not authorized to cancel this leave application.',
                ]);
            }

            return $application->refresh();
        });
    }

    /**
     * Employees may cancel only their own pending applications.
     */
    private function cancelPendingApplication(
        LeaveApplication $application,
        User $actor,
        string $reason
    ): void {
        if ($application->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => 'Only pending leave applications can be cancelled by employees.',
            ]);
        }

        $ownsApplication = $application->employee()
            ->where('user_id', $actor->id)
            ->exists();

        if (! $ownsApplication) {
            throw ValidationException::withMessages([
                'leave_application' => 'You may cancel only your own leave application.',
            ]);
        }

        $application->update([
            'status' => 'cancelled',
            'cancellation_reason' => $reason,
            'cancelled_at' => now(),
        ]);

        LeaveApplicationApproval::query()
            ->where('leave_application_id', $application->id)
            ->where('status', 'pending')
            ->update([
                'status' => 'no_longer_required',
            ]);
    }

    /**
     * Admins may cancel approved applications.
     *
     * Paid leave deductions are reversed against their original allocations.
     * LWP does not have a paid leave balance to refund.
     */
    private function cancelApprovedApplication(
        LeaveApplication $application,
        User $actor,
        string $reason
    ): void {
        if ($application->status !== 'approved') {
            throw ValidationException::withMessages([
                'status' => 'Only approved leave applications can be cancelled by an admin.',
            ]);
        }

        $leaveTypeCode = $application->leaveType?->code;

        if ($leaveTypeCode !== 'LWP') {
            $this->refundPaidLeave($application, $actor);
        }

        $application->update([
            'status' => 'cancelled',
            'cancellation_reason' => $reason,
            'cancelled_at' => now(),
        ]);
    }

    /**
     * Refund each original deduction to the same leave balance.
     */
    private function refundPaidLeave(
        LeaveApplication $application,
        User $actor
    ): void {
        $deductions = LeaveBalanceTransaction::query()
            ->where('leave_application_id', $application->id)
            ->where('transaction_type', 'deduction')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($deductions->isEmpty()) {
            throw ValidationException::withMessages([
                'leave_application' => 'No original leave deductions were found to refund.',
            ]);
        }
        $employeeId = $application->employee_id;
        $leaveTypeId = $application->leave_type_id;
        foreach ($deductions as $deduction) {
            $deductionBalance = LeaveBalance::query()
                ->whereKey($deduction->leave_balance_id)
                ->first();

            if (
                ! $deductionBalance
                || (int) $deductionBalance->employee_id !== (int) $employeeId
                || (int) $deductionBalance->leave_type_id !== (int) $leaveTypeId
            ) {
                throw ValidationException::withMessages([
                    'leave_application' => 'The original deduction does not match the employee and leave type being refunded.',
                ]);
            }
            $alreadyReversed = LeaveBalanceTransaction::query()
                ->where('reverses_transaction_id', $deduction->id)
                ->exists();

            if ($alreadyReversed) {
                throw ValidationException::withMessages([
                    'leave_application' => 'A leave deduction has already been reversed.',
                ]);
            }

            $balance = LeaveBalance::query()
                ->whereKey($deduction->leave_balance_id)
                ->lockForUpdate()
                ->firstOrFail();

            $remainingDays = (float) $balance->remaining_days;
            $deductedDays = (float) $deduction->days;
            $allocatedDays = (float) $balance->allocated_days;

            $newRemainingDays = round($remainingDays + $deductedDays, 2);

            if ($deductedDays <= 0) {
                throw ValidationException::withMessages([
                    'leave_application' => 'The original deduction amount must be greater than zero.',
                ]);
            }

            if ($newRemainingDays > $allocatedDays) {
                throw ValidationException::withMessages([
                    'leave_application' => 'The refund would exceed the original leave allocation.',
                ]);
            }

            $balance->update([
                'remaining_days' => $newRemainingDays,
            ]);

            LeaveBalanceTransaction::create([
                'leave_balance_id' => $balance->id,
                'leave_application_id' => $application->id,
                'reverses_transaction_id' => $deduction->id,
                'transaction_type' => 'reversal',
                'days' => $deduction->days,
                'balance_after' => $newRemainingDays,
                'description' => sprintf(
                    'Refund for cancelled leave application #%d; original deduction #%d.',
                    $application->id,
                    $deduction->id
                ),
                'created_by' => $actor->id,
            ]);
        }
    }
}
