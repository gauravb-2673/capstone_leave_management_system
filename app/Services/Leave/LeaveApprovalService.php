<?php

namespace App\Services\Leave;

use App\Models\LeaveApplication;
use App\Models\LeaveApplicationApproval;
use App\Models\LeaveApproverMapping;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeaveApprovalService
{
    public function __construct(
        private LeaveBalanceDeductionService $deductionService,
        private LwpEligibilityService $lwpEligibilityService
    ) {}

    /**
     * Approve a pending leave application.
     */
    public function approve(
        LeaveApplicationApproval $approval,
        User $actor
    ): LeaveApplicationApproval {
        return DB::transaction(function () use ($approval, $actor) {
            /*
             * Lock the application first.
             *
             * This serializes competing decisions for the same
             * application, including decisions by different approvers.
             */
            $applicationId = LeaveApplication::query()
                ->whereHas('approvals', function ($query) use ($approval) {
                    $query->whereKey($approval->id);
                })
                ->value('id');

            if ($applicationId === null) {
                throw ValidationException::withMessages([
                    'approval' => ['The approval task does not exist.'],
                ]);
            }

            $application = LeaveApplication::query()
                ->whereKey($applicationId)
                ->lockForUpdate()
                ->firstOrFail();

            $task = LeaveApplicationApproval::query()
                ->whereKey($approval->id)
                ->where('leave_application_id', $application->id)
                ->lockForUpdate()
                ->firstOrFail();

            $mapping = LeaveApproverMapping::query()
                ->whereKey($task->leave_approver_id)
                ->lockForUpdate()
                ->firstOrFail();

            $employee = $application->employee()
                ->lockForUpdate()
                ->firstOrFail();

            /*
 * Authorization is determined by the active approver mapping,
 * not by the actor's application role.
 */

            if (
                ! $mapping->is_active
                || (int) $mapping->approver_user_id !== (int) $actor->id
                || (int) $mapping->employee_id !== (int) $employee->id
            ) {
                throw ValidationException::withMessages([
                    'approval' => [
                        'You are not authorized to approve this application.',
                    ],
                ]);
            }

            /*
             * Only pending tasks for pending applications may be approved.
             */
            if (
                $task->status !== 'pending'
                || $application->status !== 'pending'
            ) {
                throw ValidationException::withMessages([
                    'approval' => [
                        'This application or approval task is no longer pending.',
                    ],
                ]);
            }

            if (! $employee->is_active) {
                throw ValidationException::withMessages([
                    'approval' => [
                        'Inactive employees cannot have leave approved.',
                    ],
                ]);
            }

            $leaveType = $application->leaveType()->firstOrFail();

            if (! $leaveType->is_active) {
                throw ValidationException::withMessages([
                    'approval' => [
                        'The requested leave type is inactive.',
                    ],
                ]);
            }

            /*
             * LWP eligibility must be checked before changing the
             * application to approved because the eligibility service
             * counts applications that are already approved.
             *
             * The employee row lock serializes this check against
             * concurrent approval attempts for the same employee.
             */
            if ($leaveType->code === 'LWP') {
                $eligibility = $this->lwpEligibilityService->check(
                    $employee,
                    $application->leave_from_date,
                    $application->leave_to_date,
                    (float) $application->leavedeductcount
                );

                if (! $eligibility['eligible']) {
                    throw ValidationException::withMessages([
                        'application' => [
                            $eligibility['message'],
                        ],
                    ]);
                }
            }

            /*
             * Save the decision inside this transaction.
             */
            $task->status = 'approved';
            $task->decision_reason = null;
            $task->decided_at = now();
            $task->save();

            $application->status = 'approved';
            $application->save();

            /*
             * Paid leave is deducted through the existing ledger service.
             * LWP uses its service-year eligibility rule instead.
             */
            if ($leaveType->code !== 'LWP') {
                $this->deductionService->deduct(
                    $application,
                    $actor
                );
            }

            /*
             * The first final decision completes the workflow.
             * Other pending approval tasks are no longer required.
             */
            $application->approvals()
                ->whereKeyNot($task->id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'no_longer_required',
                    'updated_at' => now(),
                ]);

            return $task->fresh();
        });
    }

    /**
     * Reject a pending leave application.
     */
    public function reject(
        LeaveApplicationApproval $approval,
        User $actor,
        string $reason
    ): LeaveApplicationApproval {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => ['A rejection reason is required.'],
            ]);
        }

        return DB::transaction(function () use (
            $approval,
            $actor,
            $reason
        ) {
            /*
             * Use the same lock order as approve() to reduce deadlock risk.
             */
            $applicationId = LeaveApplication::query()
                ->whereHas('approvals', function ($query) use ($approval) {
                    $query->whereKey($approval->id);
                })
                ->value('id');

            if ($applicationId === null) {
                throw ValidationException::withMessages([
                    'approval' => ['The approval task does not exist.'],
                ]);
            }

            $application = LeaveApplication::query()
                ->whereKey($applicationId)
                ->lockForUpdate()
                ->firstOrFail();

            $task = LeaveApplicationApproval::query()
                ->whereKey($approval->id)
                ->where('leave_application_id', $application->id)
                ->lockForUpdate()
                ->firstOrFail();

            $mapping = LeaveApproverMapping::query()
                ->whereKey($task->leave_approver_id)
                ->lockForUpdate()
                ->firstOrFail();

            $employee = $application->employee()
                ->lockForUpdate()
                ->firstOrFail();


            if (
                ! $mapping->is_active
                || (int) $mapping->approver_user_id !== (int) $actor->id
                || (int) $mapping->employee_id !== (int) $employee->id
            ) {
                throw ValidationException::withMessages([
                    'approval' => [
                        'You are not authorized to reject this application.',
                    ],
                ]);
            }

            if (
                $task->status !== 'pending'
                || $application->status !== 'pending'
            ) {
                throw ValidationException::withMessages([
                    'approval' => [
                        'This application or approval task is no longer pending.',
                    ],
                ]);
            }

            $task->status = 'rejected';
            $task->decision_reason = $reason;
            $task->decided_at = now();
            $task->save();

            $application->status = 'rejected';
            $application->save();

            $application->approvals()
                ->whereKeyNot($task->id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'no_longer_required',
                    'updated_at' => now(),
                ]);

            return $task->fresh();
        });
    }
}
