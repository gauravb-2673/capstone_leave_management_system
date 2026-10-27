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
use App\Services\Leave\LeaveCancellationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LeaveCancellationTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_cancel_own_pending_application(): void
    {
        [$employeeUser, $employee, $application] =
            $this->makeLeaveApplication('CL', 'pending');

        $mapping = $this->createApproverMapping($employee);

        $approval = LeaveApplicationApproval::create([
            'leave_application_id' => $application->id,
            'leave_approver_id' => $mapping->id,
            'status' => 'pending',
        ]);

        $reason = 'My personal plans have changed.';

        $result = app(LeaveCancellationService::class)->cancel(
            $application,
            $employeeUser,
            $reason
        );

        $this->assertSame('cancelled', $result->status);
        $this->assertSame($reason, $result->cancellation_reason);
        $this->assertNotNull($result->cancelled_at);

        $this->assertDatabaseHas('leaveapplicationapprovals', [
            'id' => $approval->id,
            'status' => 'no_longer_required',
        ]);

        $this->assertDatabaseMissing('leave_balance_transactions', [
            'leave_application_id' => $application->id,
            'transaction_type' => 'reversal',
        ]);
    }

    public function test_employee_cannot_cancel_another_employees_application(): void
    {
        [,, $application] = $this->makeLeaveApplication('CL', 'pending');

        $anotherEmployee = User::factory()->create();
        $anotherEmployee->forceFill(['role' => 'employee'])->save();

        try {
            app(LeaveCancellationService::class)->cancel(
                $application,
                $anotherEmployee,
                'Trying to cancel another employee application.'
            );

            $this->fail(
                'An employee must not cancel another employee application.'
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'leave_application',
                $exception->errors()
            );
        }

        $this->assertDatabaseHas('leaveapplications', [
            'id' => $application->id,
            'status' => 'pending',
        ]);
    }

    public function test_employee_cannot_cancel_an_approved_application(): void
    {
        [$employeeUser,, $application] =
            $this->makeLeaveApplication('CL', 'approved');

        try {
            app(LeaveCancellationService::class)->cancel(
                $application,
                $employeeUser,
                'I no longer need this leave.'
            );

            $this->fail(
                'An employee must not cancel an approved application.'
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $this->assertDatabaseHas('leaveapplications', [
            'id' => $application->id,
            'status' => 'approved',
        ]);
    }

    public function test_admin_can_cancel_approved_paid_leave_and_refund_balance(): void
    {
        [, $employee, $application] =
            $this->makeLeaveApplication('CL', 'approved');

        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();

        $leaveType = $application->leaveType;

        $balance = LeaveBalance::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'allocation_type' => 'annual',
            'allocated_days' => 12.00,
            'remaining_days' => 10.00,
            'period_start' => '2026-01-15',
            'period_end' => '2027-01-14',
            'expires_at' => null,
            'source_balance_id' => null,
            'grant_key' => 'test:cancel:paid:CL',
        ]);

        $deduction = LeaveBalanceTransaction::create([
            'leave_balance_id' => $balance->id,
            'leave_application_id' => $application->id,
            'reverses_transaction_id' => null,
            'transaction_type' => 'deduction',
            'days' => 2.00,
            'balance_after' => 10.00,
            'description' => 'Original approved leave deduction.',
            'created_by' => $admin->id,
        ]);

        $result = app(LeaveCancellationService::class)->cancel(
            $application,
            $admin,
            'Leave cancelled by HR administration.'
        );

        $this->assertSame('cancelled', $result->status);

        $this->assertSame(
            '12.00',
            $balance->fresh()->remaining_days
        );

        $this->assertDatabaseHas('leave_balance_transactions', [
            'leave_balance_id' => $balance->id,
            'leave_application_id' => $application->id,
            'reverses_transaction_id' => $deduction->id,
            'transaction_type' => 'reversal',
            'days' => '2.00',
            'balance_after' => '12.00',
            'created_by' => $admin->id,
        ]);

        // The original deduction must remain unchanged.
        $this->assertDatabaseHas('leave_balance_transactions', [
            'id' => $deduction->id,
            'transaction_type' => 'deduction',
            'days' => '2.00',
        ]);
    }

    public function test_admin_can_cancel_approved_lwp_without_refunding_paid_leave(): void
    {
        [, $employee, $application] =
            $this->makeLeaveApplication('LWP', 'approved');

        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();

        $paidLeaveType = LeaveType::create([
            'code' => 'CL',
            'name' => 'Casual Leave',
            'is_active' => true,
        ]);

        $balance = LeaveBalance::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $paidLeaveType->id,
            'allocation_type' => 'annual',
            'allocated_days' => 12.00,
            'remaining_days' => 12.00,
            'period_start' => '2026-01-15',
            'period_end' => '2027-01-14',
            'expires_at' => null,
            'source_balance_id' => null,
            'grant_key' => 'test:cancel:lwp:CL',
        ]);

        $result = app(LeaveCancellationService::class)->cancel(
            $application,
            $admin,
            'The unpaid leave is no longer required.'
        );

        $this->assertSame('cancelled', $result->status);

        $this->assertSame(
            '12.00',
            $balance->fresh()->remaining_days
        );

        $this->assertDatabaseMissing('leave_balance_transactions', [
            'leave_application_id' => $application->id,
            'transaction_type' => 'reversal',
        ]);
    }

    public function test_cancelled_application_cannot_be_cancelled_again(): void
    {
        [, $employee, $application] =
            $this->makeLeaveApplication('CL', 'approved');

        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();

        $balance = LeaveBalance::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $application->leave_type_id,
            'allocation_type' => 'annual',
            'allocated_days' => 12.00,
            'remaining_days' => 10.00,
            'period_start' => '2026-01-15',
            'period_end' => '2027-01-14',
            'expires_at' => null,
            'source_balance_id' => null,
            'grant_key' => 'test:cancel:duplicate:CL',
        ]);

        $deduction = LeaveBalanceTransaction::create([
            'leave_balance_id' => $balance->id,
            'leave_application_id' => $application->id,
            'reverses_transaction_id' => null,
            'transaction_type' => 'deduction',
            'days' => 2.00,
            'balance_after' => 10.00,
            'description' => 'Original approved leave deduction.',
            'created_by' => $admin->id,
        ]);

        $service = app(LeaveCancellationService::class);

        $service->cancel(
            $application,
            $admin,
            'First cancellation.'
        );

        try {
            $service->cancel(
                $application,
                $admin,
                'Second cancellation attempt.'
            );

            $this->fail(
                'A cancelled application must not be cancelled again.'
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $this->assertSame(
            '12.00',
            $balance->fresh()->remaining_days
        );

        $this->assertSame(
            1,
            LeaveBalanceTransaction::query()
                ->where('reverses_transaction_id', $deduction->id)
                ->where('transaction_type', 'reversal')
                ->count()
        );
    }

    private function makeLeaveApplication(
        string $leaveTypeCode,
        string $status
    ): array {
        $employeeUser = User::factory()->create();
        $employeeUser->forceFill(['role' => 'employee'])->save();

        $employee = Employee::create([
            'user_id' => $employeeUser->id,
            'department_id' => null,
            'employee_code' => 'EMP-CANCEL-' . $employeeUser->id,
            'joining_date' => '2025-01-15',
            'is_active' => true,
        ]);

        $leaveType = LeaveType::create([
            'code' => $leaveTypeCode,
            'name' => $leaveTypeCode === 'LWP'
                ? 'Leave Without Pay'
                : 'Casual Leave',
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
            'status' => $status,
        ]);

        return [$employeeUser, $employee, $application];
    }

    private function createApproverMapping(Employee $employee): LeaveApproverMapping
    {
        $approver = User::factory()->create();
        $approver->forceFill(['role' => 'approver'])->save();

        return LeaveApproverMapping::create([
            'employee_id' => $employee->id,
            'approver_user_id' => $approver->id,
            'is_active' => true,
        ]);
    }
    public function test_admin_cannot_refund_deduction_from_another_leave_type(): void
    {
        [, $employee, $application] =
            $this->makeLeaveApplication('CL', 'approved');

        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();

        $sickLeaveType = LeaveType::create([
            'code' => 'SL',
            'name' => 'Sick Leave',
            'is_active' => true,
        ]);

        // This balance belongs to the employee but is for Sick Leave,
        // while the application being cancelled is for Casual Leave.
        $balance = LeaveBalance::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $sickLeaveType->id,
            'allocation_type' => 'annual',
            'allocated_days' => 6.00,
            'remaining_days' => 4.00,
            'period_start' => '2026-01-15',
            'period_end' => '2027-01-14',
            'expires_at' => null,
            'source_balance_id' => null,
            'grant_key' => 'test:cancel:mismatched:SL',
        ]);

        LeaveBalanceTransaction::create([
            'leave_balance_id' => $balance->id,
            'leave_application_id' => $application->id,
            'reverses_transaction_id' => null,
            'transaction_type' => 'deduction',
            'days' => 2.00,
            'balance_after' => 4.00,
            'description' => 'Intentionally mismatched deduction.',
            'created_by' => $admin->id,
        ]);

        try {
            app(LeaveCancellationService::class)->cancel(
                $application,
                $admin,
                'Cancel this application.'
            );

            $this->fail(
                'Cancellation must fail when the deduction belongs to another leave type.'
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'leave_application',
                $exception->errors()
            );
        }

        // The failed cancellation must leave the application approved.
        $this->assertDatabaseHas('leaveapplications', [
            'id' => $application->id,
            'status' => 'approved',
        ]);

        // The original balance must remain unchanged.
        $this->assertSame(
            '4.00',
            $balance->fresh()->remaining_days
        );

        // No refund transaction should have been created.
        $this->assertDatabaseMissing('leave_balance_transactions', [
            'leave_application_id' => $application->id,
            'transaction_type' => 'reversal',
        ]);
    }
}
