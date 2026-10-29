<?php

namespace Tests\Feature;

use App\Models\Duration;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveApplicationApproval;
use App\Models\LeaveApproverMapping;
use App\Models\LeaveBalance;
use App\Models\LeaveBalanceTransaction;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\Leave\LeaveApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LeaveApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_approver_can_approve_paid_leave_and_balance_is_deducted_once(): void
    {
        /*
         * 1. Create the employee and approver users.
         */
        $employeeUser = User::factory()->create();

        $approverUser = User::factory()->create();

        // role is deliberately excluded from User::$fillable.
        // forceFill lets this test assign the role explicitly.
        $approverUser->forceFill([
            'role' => 'approver',
        ])->save();

        /*
         * 2. Create the employee profile.
         */
        $employee = Employee::create([
            'user_id' => $employeeUser->id,
            'department_id' => null,
            'employee_code' => 'EMP-APPROVAL-001',
            'joining_date' => '2025-01-15',
            'is_active' => true,
        ]);

        /*
         * 3. Create the active Casual Leave type and duration.
         */
        $casualLeave = LeaveType::create([
            'code' => 'CL',
            'name' => 'Casual Leave',
            'is_active' => true,
        ]);

        $multipleDays = Duration::create([
            'name' => 'Multiple Days',
            'days' => 1.00,
        ]);

        /*
         * 4. Allocate 12 days of Casual Leave.
         */
        $balance = LeaveBalance::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $casualLeave->id,
            'allocation_type' => 'annual',
            'allocated_days' => 12.00,
            'remaining_days' => 12.00,
            'period_start' => '2026-01-15',
            'period_end' => '2027-01-14',
            'expires_at' => null,
            'source_balance_id' => null,
            'grant_key' => 'test:employee:EMP-APPROVAL-001:CL:2026',
        ]);

        /*
         * 5. Create a pending two-day leave application.
         *
         * October 26 and 27, 2026 are Monday and Tuesday.
         * Neither date is a weekend.
         */
        $application = LeaveApplication::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $casualLeave->id,
            'duration_id' => $multipleDays->id,
            'half_day_period' => null,
            'leave_from_date' => '2026-10-26',
            'leave_to_date' => '2026-10-27',
            'leavedeductcount' => 2.00,
            'leavereason' => 'Personal work',
            'status' => 'pending',
        ]);

        /*
         * 6. Assign the approver to this employee.
         */
        $mapping = LeaveApproverMapping::create([
            'employee_id' => $employee->id,
            'approver_user_id' => $approverUser->id,
            'is_active' => true,
        ]);

        /*
         * 7. Create the pending approval task.
         */
        $approval = LeaveApplicationApproval::create([
            'leave_application_id' => $application->id,
            'leave_approver_id' => $mapping->id,
            'status' => 'pending',
            'decision_reason' => null,
            'decided_at' => null,
        ]);

        /*
         * 8. Execute the approval workflow.
         */
        $result = app(LeaveApprovalService::class)->approve(
            $approval,
            $approverUser
        );

        // A second approval attempt must fail because the task is no longer pending.
        try {
            app(LeaveApprovalService::class)->approve(
                $approval,
                $approverUser
            );

            $this->fail(
                'An already-approved leave application must not be approved again.'
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('approval', $exception->errors());
        }

        /*
         * 9. Verify the approval decision.
         */
        $this->assertSame('approved', $result->status);

        $this->assertDatabaseHas('leaveapplications', [
            'id' => $application->id,
            'status' => 'approved',
        ]);

        $this->assertDatabaseHas('leaveapplicationapprovals', [
            'id' => $approval->id,
            'status' => 'approved',
        ]);

        /*
         * 10. Verify the remaining leave balance.
         */
        $this->assertSame(
            '10.00',
            $balance->fresh()->remaining_days
        );

        $this->assertDatabaseHas('leavebalances', [
            'id' => $balance->id,
            'remaining_days' => '10.00',
        ]);

        /*
         * 11. Verify the deduction ledger entry.
         */
        $transaction = LeaveBalanceTransaction::query()
            ->where('leave_application_id', $application->id)
            ->where('leave_balance_id', $balance->id)
            ->where('transaction_type', 'deduction')
            ->first();

        $this->assertNotNull(
            $transaction,
            'An approval must create a deduction transaction.'
        );

        $this->assertSame('2.00', $transaction->days);
        $this->assertSame('10.00', $transaction->balance_after);
        $this->assertSame($approverUser->id, $transaction->created_by);

        // Exactly one deduction transaction must exist for this application.
        $this->assertSame(
            1,
            LeaveBalanceTransaction::query()
                ->where('leave_application_id', $application->id)
                ->where('transaction_type', 'deduction')
                ->count()
        );

        // The repeated attempt must not deduct another two days.
        $this->assertSame('10.00', $balance->fresh()->remaining_days);
    }

    public function test_approval_fails_when_leave_balance_is_insufficient(): void
    {
        $employeeUser = User::factory()->create();

        $approverUser = User::factory()->create();

        $approverUser->forceFill([
            'role' => 'approver',
        ])->save();

        $employee = Employee::create([
            'user_id' => $employeeUser->id,
            'department_id' => null,
            'employee_code' => 'EMP-APPROVAL-002',
            'joining_date' => '2025-01-15',
            'is_active' => true,
        ]);

        $casualLeave = LeaveType::create([
            'code' => 'CL',
            'name' => 'Casual Leave',
            'is_active' => true,
        ]);

        $multipleDays = Duration::create([
            'name' => 'Multiple Days',
            'days' => 1.00,
        ]);

        // The employee has only one day available.
        $balance = LeaveBalance::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $casualLeave->id,
            'allocation_type' => 'annual',
            'allocated_days' => 1.00,
            'remaining_days' => 1.00,
            'period_start' => '2026-01-15',
            'period_end' => '2027-01-14',
            'expires_at' => null,
            'source_balance_id' => null,
            'grant_key' => 'test:employee:EMP-APPROVAL-002:CL:2026',
        ]);

        // The employee requests two working days.
        $application = LeaveApplication::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $casualLeave->id,
            'duration_id' => $multipleDays->id,
            'half_day_period' => null,
            'leave_from_date' => '2026-10-26',
            'leave_to_date' => '2026-10-27',
            'leavedeductcount' => 2.00,
            'leavereason' => 'Personal work',
            'status' => 'pending',
        ]);

        $mapping = LeaveApproverMapping::create([
            'employee_id' => $employee->id,
            'approver_user_id' => $approverUser->id,
            'is_active' => true,
        ]);

        $approval = LeaveApplicationApproval::create([
            'leave_application_id' => $application->id,
            'leave_approver_id' => $mapping->id,
            'status' => 'pending',
            'decision_reason' => null,
            'decided_at' => null,
        ]);

        // The service should throw when it cannot deduct two days.
        try {
            app(LeaveApprovalService::class)->approve(
                $approval,
                $approverUser
            );

            $this->fail(
                'Approval should fail when the balance is insufficient.'
            );
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }

        // The application must remain pending.
        $this->assertDatabaseHas('leaveapplications', [
            'id' => $application->id,
            'status' => 'pending',
        ]);

        // The approval task must remain pending.
        $this->assertDatabaseHas('leaveapplicationapprovals', [
            'id' => $approval->id,
            'status' => 'pending',
        ]);

        // No balance may be deducted.
        $this->assertSame(
            '1.00',
            $balance->fresh()->remaining_days
        );

        // No deduction ledger transaction may remain.
        $this->assertDatabaseMissing('leave_balance_transactions', [
            'leave_application_id' => $application->id,
            'transaction_type' => 'deduction',
        ]);
    }

    public function test_approving_lwp_does_not_deduct_paid_leave_balance(): void
    {
        $employeeUser = User::factory()->create();

        $approverUser = User::factory()->create();
        $approverUser->forceFill(['role' => 'approver'])->save();

        $employee = Employee::factory()->create([
            'user_id' => $employeeUser->id,
            'employee_code' => 'EMP-LWP-001',
            'joining_date' => '2024-01-15',
            'is_active' => true,
        ]);

        $lwpType = LeaveType::firstOrCreate(
            ['code' => 'LWP'],
            [
                'name' => 'Leave Without Pay',
                'is_active' => true,
            ]
        );

        $duration = Duration::firstOrCreate(
            ['name' => 'Multiple Days'],
            ['days' => 1.00]
        );

        $paidLeaveType = LeaveType::firstOrCreate(
            ['code' => 'CL'],
            [
                'name' => 'Casual Leave',
                'is_active' => true,
            ]
        );

        $balance = LeaveBalance::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $paidLeaveType->id,
            'allocation_type' => 'annual',
            'allocated_days' => 12.00,
            'remaining_days' => 12.00,
            'period_start' => '2026-01-15',
            'period_end' => '2027-01-14',
            'expires_at' => '2027-01-14',
            'source_balance_id' => null,
            'grant_key' => 'test:employee:EMP-LWP-001:CL:2026',
        ]);

        $application = LeaveApplication::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $lwpType->id,
            'duration_id' => $duration->id,
            'half_day_period' => null,
            'leave_from_date' => '2026-10-26',
            'leave_to_date' => '2026-10-27',
            'leavedeductcount' => 2.00,
            'leavereason' => 'Personal work',
            'status' => 'pending',
        ]);

        $mapping = LeaveApproverMapping::create([
            'employee_id' => $employee->id,
            'approver_user_id' => $approverUser->id,
            'is_active' => true,
        ]);

        $approval = LeaveApplicationApproval::create([
            'leave_application_id' => $application->id,
            'leave_approver_id' => $mapping->id,
            'status' => 'pending',
        ]);

        $service = app(LeaveApprovalService::class);

        $result = $service->approve($approval, $approverUser);

        $this->assertSame('approved', $result->fresh()->status);
        $this->assertSame('approved', $application->fresh()->status);

        $this->assertSame(
            '12.00',
            $balance->fresh()->remaining_days
        );
        $this->assertDatabaseMissing('leave_balance_transactions', [
            'leave_application_id' => $application->id,
            'transaction_type' => 'deduction',
        ]);
    }

    public function test_rejection_fails_when_reason_is_empty(): void
    {
        $employeeUser = User::factory()->create();

        $approverUser = User::factory()->create();
        $approverUser->forceFill([
            'role' => 'approver',
        ])->save();

        $employee = Employee::create([
            'user_id' => $employeeUser->id,
            'department_id' => null,
            'employee_code' => 'EMP-REJECT-001',
            'joining_date' => '2025-01-15',
            'is_active' => true,
        ]);

        $leaveType = LeaveType::create([
            'code' => 'CL',
            'name' => 'Casual Leave',
            'is_active' => true,
        ]);

        $duration = Duration::create([
            'name' => 'Multiple Days',
            'days' => 1.00,
        ]);

        $application = LeaveApplication::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'duration_id' => $duration->id,
            'half_day_period' => null,
            'leave_from_date' => '2026-10-26',
            'leave_to_date' => '2026-10-27',
            'leavedeductcount' => 2.00,
            'leavereason' => 'Personal work',
            'status' => 'pending',
        ]);

        $mapping = LeaveApproverMapping::create([
            'employee_id' => $employee->id,
            'approver_user_id' => $approverUser->id,
            'is_active' => true,
        ]);

        $approval = LeaveApplicationApproval::create([
            'leave_application_id' => $application->id,
            'leave_approver_id' => $mapping->id,
            'status' => 'pending',
            'decision_reason' => null,
            'decided_at' => null,
        ]);

        try {
            app(LeaveApprovalService::class)->reject(
                $approval,
                $approverUser,
                '   '
            );

            $this->fail('An empty rejection reason should not be accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'reason',
                $exception->errors()
            );
        }

        $this->assertDatabaseHas('leaveapplications', [
            'id' => $application->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('leaveapplicationapprovals', [
            'id' => $approval->id,
            'status' => 'pending',
        ]);
    }

    public function test_rejection_saves_reason_and_closes_other_pending_approvals(): void
    {
        $employeeUser = User::factory()->create();

        $firstApprover = User::factory()->create();
        $firstApprover->forceFill([
            'role' => 'approver',
        ])->save();

        $secondApprover = User::factory()->create();
        $secondApprover->forceFill([
            'role' => 'approver',
        ])->save();

        $employee = Employee::create([
            'user_id' => $employeeUser->id,
            'department_id' => null,
            'employee_code' => 'EMP-REJECT-002',
            'joining_date' => '2025-01-15',
            'is_active' => true,
        ]);

        $leaveType = LeaveType::create([
            'code' => 'CL',
            'name' => 'Casual Leave',
            'is_active' => true,
        ]);

        $duration = Duration::create([
            'name' => 'Multiple Days',
            'days' => 1.00,
        ]);

        $application = LeaveApplication::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'duration_id' => $duration->id,
            'half_day_period' => null,
            'leave_from_date' => '2026-10-26',
            'leave_to_date' => '2026-10-27',
            'leavedeductcount' => 2.00,
            'leavereason' => 'Personal work',
            'status' => 'pending',
        ]);

        $firstMapping = LeaveApproverMapping::create([
            'employee_id' => $employee->id,
            'approver_user_id' => $firstApprover->id,
            'is_active' => true,
        ]);

        $secondMapping = LeaveApproverMapping::create([
            'employee_id' => $employee->id,
            'approver_user_id' => $secondApprover->id,
            'is_active' => true,
        ]);

        $firstTask = LeaveApplicationApproval::create([
            'leave_application_id' => $application->id,
            'leave_approver_id' => $firstMapping->id,
            'status' => 'pending',
            'decision_reason' => null,
            'decided_at' => null,
        ]);

        $secondTask = LeaveApplicationApproval::create([
            'leave_application_id' => $application->id,
            'leave_approver_id' => $secondMapping->id,
            'status' => 'pending',
            'decision_reason' => null,
            'decided_at' => null,
        ]);

        $reason = 'Insufficient team coverage on the requested dates.';

        $result = app(LeaveApprovalService::class)->reject(
            $firstTask,
            $firstApprover,
            $reason
        );

        $this->assertSame('rejected', $result->status);
        $this->assertSame($reason, $result->decision_reason);
        $this->assertNotNull($result->decided_at);

        $this->assertDatabaseHas('leaveapplications', [
            'id' => $application->id,
            'status' => 'rejected',
        ]);

        $this->assertDatabaseHas('leaveapplicationapprovals', [
            'id' => $firstTask->id,
            'status' => 'rejected',
            'decision_reason' => $reason,
        ]);

        $this->assertDatabaseHas('leaveapplicationapprovals', [
            'id' => $secondTask->id,
            'status' => 'no_longer_required',
        ]);

        $this->assertDatabaseMissing('leave_balance_transactions', [
            'leave_application_id' => $application->id,
            'transaction_type' => 'deduction',
        ]);
    }

    public function test_approved_leave_cannot_be_rejected_after_approval(): void
    {
        $employeeUser = User::factory()->create();

        $approverUser = User::factory()->create();
        $approverUser->forceFill(['role' => 'approver'])->save();

        $employee = Employee::create([
            'user_id' => $employeeUser->id,
            'department_id' => null,
            'employee_code' => 'EMP-REJECT-AFTER-APPROVAL-001',
            'joining_date' => '2025-01-15',
            'is_active' => true,
        ]);

        $leaveType = LeaveType::create([
            'code' => 'CL',
            'name' => 'Casual Leave',
            'is_active' => true,
        ]);

        $duration = Duration::create([
            'name' => 'Multiple Days',
            'days' => 1.00,
        ]);

        $application = LeaveApplication::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'duration_id' => $duration->id,
            'half_day_period' => null,
            'leave_from_date' => '2026-10-26',
            'leave_to_date' => '2026-10-27',
            'leavedeductcount' => 2.00,
            'leavereason' => 'Personal work',
            'status' => 'approved',
        ]);

        $mapping = LeaveApproverMapping::create([
            'employee_id' => $employee->id,
            'approver_user_id' => $approverUser->id,
            'is_active' => true,
        ]);

        $approval = LeaveApplicationApproval::create([
            'leave_application_id' => $application->id,
            'leave_approver_id' => $mapping->id,
            'status' => 'approved',
            'decision_reason' => null,
            'decided_at' => now(),
        ]);

        try {
            app(LeaveApprovalService::class)->reject(
                $approval,
                $approverUser,
                'Changed my mind'
            );

            $this->fail('An already-approved application must not be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('approval', $exception->errors());
        }

        $this->assertDatabaseHas('leaveapplications', [
            'id' => $application->id,
            'status' => 'approved',
        ]);

        $this->assertDatabaseHas('leaveapplicationapprovals', [
            'id' => $approval->id,
            'status' => 'approved',
        ]);
    }

    public function test_unassigned_approver_cannot_approve_leave(): void
    {
        $employeeUser = User::factory()->create();

        $assignedApprover = User::factory()->create();
        $assignedApprover->forceFill([
            'role' => 'approver',
        ])->save();

        $unassignedApprover = User::factory()->create();
        $unassignedApprover->forceFill([
            'role' => 'approver',
        ])->save();

        $employee = Employee::create([
            'user_id' => $employeeUser->id,
            'department_id' => null,
            'employee_code' => 'EMP-AUTH-001',
            'joining_date' => '2025-01-15',
            'is_active' => true,
        ]);

        $leaveType = LeaveType::create([
            'code' => 'CL',
            'name' => 'Casual Leave',
            'is_active' => true,
        ]);

        $duration = Duration::create([
            'name' => 'Multiple Days',
            'days' => 1.00,
        ]);

        $application = LeaveApplication::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'duration_id' => $duration->id,
            'half_day_period' => null,
            'leave_from_date' => '2026-10-26',
            'leave_to_date' => '2026-10-27',
            'leavedeductcount' => 2.00,
            'leavereason' => 'Personal work',
            'status' => 'pending',
        ]);

        $mapping = LeaveApproverMapping::create([
            'employee_id' => $employee->id,
            'approver_user_id' => $assignedApprover->id,
            'is_active' => true,
        ]);

        $approval = LeaveApplicationApproval::create([
            'leave_application_id' => $application->id,
            'leave_approver_id' => $mapping->id,
            'status' => 'pending',
            'decision_reason' => null,
            'decided_at' => null,
        ]);

        try {
            app(LeaveApprovalService::class)->approve(
                $approval,
                $unassignedApprover
            );

            $this->fail('An unassigned approver must not approve this application.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'approval',
                $exception->errors()
            );

            $this->assertSame(
                'You are not authorized to approve this application.',
                $exception->errors()['approval'][0]
            );
        }

        $this->assertDatabaseHas('leaveapplications', [
            'id' => $application->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('leaveapplicationapprovals', [
            'id' => $approval->id,
            'status' => 'pending',
        ]);
    }


    public function test_assigned_employee_can_approve_leave_without_approver_role(): void
    {
        $employeeUser = User::factory()->create();

        $employeeUser->forceFill([
            'role' => 'employee',
        ])->save();

        $leaveApplicant = User::factory()->create();

        $leaveApplicantProfile = Employee::create([
            'user_id' => $leaveApplicant->id,
            'department_id' => null,
            'employee_code' => 'EMP-AUTH-002',
            'joining_date' => '2025-01-15',
            'is_active' => true,
        ]);

        $leaveType = LeaveType::create([
            'code' => 'CL',
            'name' => 'Casual Leave',
            'is_active' => true,
        ]);

        $duration = Duration::create([
            'name' => 'Multiple Days',
            'days' => 1.00,
        ]);
        $balance = LeaveBalance::create([
            'employee_id' => $leaveApplicantProfile->id,
            'leave_type_id' => $leaveType->id,
            'allocation_type' => 'annual',
            'allocated_days' => 12.00,
            'remaining_days' => 12.00,
            'period_start' => '2026-01-15',
            'period_end' => '2027-01-14',
            'expires_at' => null,
            'source_balance_id' => null,
            'grant_key' => 'test:employee:EMP-AUTH-002:CL:2026',
        ]);

        $application = LeaveApplication::create([
            'employee_id' => $leaveApplicantProfile->id,
            'leave_type_id' => $leaveType->id,
            'duration_id' => $duration->id,
            'half_day_period' => null,
            'leave_from_date' => '2026-10-26',
            'leave_to_date' => '2026-10-27',
            'leavedeductcount' => 2.00,
            'leavereason' => 'Personal work',
            'status' => 'pending',
        ]);

        $mapping = LeaveApproverMapping::create([
            'employee_id' => $leaveApplicantProfile->id,
            'approver_user_id' => $employeeUser->id,
            'is_active' => true,
        ]);

        $approval = LeaveApplicationApproval::create([
            'leave_application_id' => $application->id,
            'leave_approver_id' => $mapping->id,
            'status' => 'pending',
            'decision_reason' => null,
            'decided_at' => null,
        ]);

        app(LeaveApprovalService::class)->approve(
            $approval,
            $employeeUser
        );

        $this->assertDatabaseHas('leaveapplications', [
            'id' => $application->id,
            'status' => 'approved',
        ]);

        $this->assertDatabaseHas('leaveapplicationapprovals', [
            'id' => $approval->id,
            'status' => 'approved',
        ]);
    }
}
