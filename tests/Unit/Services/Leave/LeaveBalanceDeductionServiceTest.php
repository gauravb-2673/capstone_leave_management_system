<?php

namespace Tests\Unit\Services\Leave;

use App\Models\Duration;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveApplication;
use App\Models\LeaveBalance;
use App\Models\LeaveBalanceTransaction;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\Leave\LeaveBalanceDeductionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LeaveBalanceDeductionServiceTest extends TestCase
{
    use RefreshDatabase;

    private Employee $employee;

    private User $actor;

    private LeaveType $cl;

    private Duration $fullDay;

    private Duration $halfDay;

    private Duration $multipleDays;

    private LeaveBalanceDeductionService $deductionService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actor = User::factory()->create();

        $this->employee = Employee::create([
            'user_id' => $this->actor->id,
            'department_id' => null,
            'employee_code' => 'TEST001',
            'joining_date' => '2025-01-15',
            'is_active' => true,
        ]);

        $this->cl = LeaveType::create([
            'code' => 'CL',
            'name' => 'Casual Leave',
            'is_active' => true,
        ]);

        $this->fullDay = Duration::create([
            'name' => 'Full Day',
            'days' => '1.00',
        ]);

        $this->halfDay = Duration::create([
            'name' => 'Half Day',
            'days' => '0.50',
        ]);

        $this->multipleDays = Duration::create([
            'name' => 'Multiple Days',
            'days' => '1.00',
        ]);

        $this->deductionService = app(
            LeaveBalanceDeductionService::class
        );
    }

    public function test_approved_full_day_leave_deducts_one_day_and_records_ledger(): void
    {
        $balance = $this->createBalance(
            remainingDays: '5.00',
            periodStart: '2026-01-15',
            periodEnd: '2027-01-14',
        );

        $application = $this->createLeaveApplication(
            from: '2026-10-26',
            to: '2026-10-26',
            requestedDays: '1.00',
            duration: $this->fullDay,
        );

        $results = $this->deductionService->deduct(
            $application,
            $this->actor
        );

        $this->assertCount(1, $results);
        $this->assertSame($balance->id, $results[0]['balance_id']);
        $this->assertSame('1.00', $results[0]['deducted_days']);
        $this->assertSame('4.00', $results[0]['remaining_days']);

        $this->assertDatabaseHas('leavebalances', [
            'id' => $balance->id,
            'remaining_days' => '4.00',
        ]);

        $this->assertDatabaseHas('leave_balance_transactions', [
            'leave_balance_id' => $balance->id,
            'leave_application_id' => $application->id,
            'transaction_type' => 'deduction',
            'days' => '1.00',
            'balance_after' => '4.00',
            'created_by' => $this->actor->id,
        ]);
    }

    public function test_half_day_leave_deducts_exactly_half_a_day(): void
    {
        $balance = $this->createBalance(
            remainingDays: '2.00',
            periodStart: '2026-01-15',
            periodEnd: '2027-01-14',
        );

        $application = $this->createLeaveApplication(
            from: '2026-10-26',
            to: '2026-10-26',
            requestedDays: '0.50',
            duration: $this->halfDay,
            halfDayPeriod: 'first_half',
        );

        $results = $this->deductionService->deduct(
            $application,
            $this->actor
        );

        $this->assertSame('0.50', $results[0]['deducted_days']);
        $this->assertSame('1.50', $results[0]['remaining_days']);

        $this->assertDatabaseHas('leavebalances', [
            'id' => $balance->id,
            'remaining_days' => '1.50',
        ]);

        $this->assertDatabaseHas('leave_balance_transactions', [
            'leave_balance_id' => $balance->id,
            'leave_application_id' => $application->id,
            'transaction_type' => 'deduction',
            'days' => '0.50',
            'balance_after' => '1.50',
        ]);
    }

    public function test_leave_is_deducted_from_multiple_allocations_when_needed(): void
    {
        $firstBalance = $this->createBalance(
            remainingDays: '1.00',
            periodStart: '2026-01-15',
            periodEnd: '2027-01-14',
            expiresAt: '2026-12-31',
            grantKey: 'test:CL:first-allocation',
        );

        $secondBalance = $this->createBalance(
            remainingDays: '2.00',
            periodStart: '2026-01-15',
            periodEnd: '2027-01-14',
            expiresAt: '2027-01-14',
            grantKey: 'test:CL:second-allocation',
        );

        $application = $this->createLeaveApplication(
            from: '2026-10-26',
            to: '2026-10-27',
            requestedDays: '2.00',
            duration: $this->multipleDays,
        );

        $results = $this->deductionService->deduct(
            $application,
            $this->actor
        );

        $this->assertCount(2, $results);

        $this->assertDatabaseHas('leavebalances', [
            'id' => $firstBalance->id,
            'remaining_days' => '0.00',
        ]);

        $this->assertDatabaseHas('leavebalances', [
            'id' => $secondBalance->id,
            'remaining_days' => '1.00',
        ]);

        $this->assertDatabaseHas('leave_balance_transactions', [
            'leave_balance_id' => $firstBalance->id,
            'leave_application_id' => $application->id,
            'transaction_type' => 'deduction',
            'days' => '1.00',
            'balance_after' => '0.00',
        ]);

        $this->assertDatabaseHas('leave_balance_transactions', [
            'leave_balance_id' => $secondBalance->id,
            'leave_application_id' => $application->id,
            'transaction_type' => 'deduction',
            'days' => '1.00',
            'balance_after' => '1.00',
        ]);
    }

    public function test_expired_allocation_is_not_used_for_leave_date(): void
    {
        $expiredBalance = $this->createBalance(
            remainingDays: '5.00',
            periodStart: '2026-01-15',
            periodEnd: '2027-01-14',
            expiresAt: '2026-10-25',
        );

        $application = $this->createLeaveApplication(
            from: '2026-10-26',
            to: '2026-10-26',
            requestedDays: '1.00',
            duration: $this->fullDay,
        );

        try {
            $this->deductionService->deduct(
                $application,
                $this->actor
            );

            $this->fail('An expired allocation must not fund leave.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString(
                'Insufficient eligible leave balance',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseHas('leavebalances', [
            'id' => $expiredBalance->id,
            'remaining_days' => '5.00',
        ]);

        $this->assertDatabaseMissing('leave_balance_transactions', [
            'leave_application_id' => $application->id,
            'transaction_type' => 'deduction',
        ]);
    }

    public function test_insufficient_balance_rolls_back_partial_deductions(): void
    {
        $firstBalance = $this->createBalance(
            remainingDays: '1.00',
            periodStart: '2026-01-15',
            periodEnd: '2027-01-14',
            expiresAt: '2026-12-31',
            grantKey: 'test:CL:rollback-first',
        );

        $secondBalance = $this->createBalance(
            remainingDays: '0.00',
            periodStart: '2026-01-15',
            periodEnd: '2027-01-14',
            expiresAt: '2027-01-14',
            grantKey: 'test:CL:rollback-second',
        );

        $application = $this->createLeaveApplication(
            from: '2026-10-26',
            to: '2026-10-27',
            requestedDays: '2.00',
            duration: $this->multipleDays,
        );

        try {
            $this->deductionService->deduct(
                $application,
                $this->actor
            );

            $this->fail('Insufficient balance must reject the deduction.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString(
                'Insufficient eligible leave balance',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseHas('leavebalances', [
            'id' => $firstBalance->id,
            'remaining_days' => '1.00',
        ]);

        $this->assertDatabaseHas('leavebalances', [
            'id' => $secondBalance->id,
            'remaining_days' => '0.00',
        ]);

        $this->assertDatabaseMissing('leave_balance_transactions', [
            'leave_application_id' => $application->id,
            'transaction_type' => 'deduction',
        ]);
    }

    public function test_same_application_cannot_be_deducted_twice(): void
    {
        $balance = $this->createBalance(
            remainingDays: '5.00',
            periodStart: '2026-01-15',
            periodEnd: '2027-01-14',
        );

        $application = $this->createLeaveApplication(
            from: '2026-10-26',
            to: '2026-10-26',
            requestedDays: '1.00',
            duration: $this->fullDay,
        );

        $this->deductionService->deduct(
            $application,
            $this->actor
        );

        try {
            $this->deductionService->deduct(
                $application,
                $this->actor
            );

            $this->fail('A second deduction must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString(
                'already had its balance deducted',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseHas('leavebalances', [
            'id' => $balance->id,
            'remaining_days' => '4.00',
        ]);

        $this->assertSame(
            1,
            LeaveBalanceTransaction::query()
                ->where('leave_application_id', $application->id)
                ->where('transaction_type', 'deduction')
                ->count()
        );
    }

    public function test_unapproved_application_cannot_be_deducted(): void
    {
        $balance = $this->createBalance(
            remainingDays: '5.00',
            periodStart: '2026-01-15',
            periodEnd: '2027-01-14',
        );

        $application = $this->createLeaveApplication(
            from: '2026-10-26',
            to: '2026-10-26',
            requestedDays: '1.00',
            duration: $this->fullDay,
            status: 'pending',
        );

        try {
            $this->deductionService->deduct(
                $application,
                $this->actor
            );

            $this->fail('An unapproved application must not be deducted.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString(
                'Only approved leave applications',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseHas('leavebalances', [
            'id' => $balance->id,
            'remaining_days' => '5.00',
        ]);

        $this->assertDatabaseMissing('leave_balance_transactions', [
            'leave_application_id' => $application->id,
            'transaction_type' => 'deduction',
        ]);
    }

    public function test_inactive_employee_cannot_have_balance_deducted(): void
    {
        $balance = $this->createBalance(
            remainingDays: '5.00',
            periodStart: '2026-01-15',
            periodEnd: '2027-01-14',
        );

        $this->employee->update([
            'is_active' => false,
        ]);

        $application = $this->createLeaveApplication(
            from: '2026-10-26',
            to: '2026-10-26',
            requestedDays: '1.00',
            duration: $this->fullDay,
        );

        try {
            $this->deductionService->deduct(
                $application,
                $this->actor
            );

            $this->fail('An inactive employee must not be deducted.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString(
                'Inactive employees',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseHas('leavebalances', [
            'id' => $balance->id,
            'remaining_days' => '5.00',
        ]);

        $this->assertDatabaseMissing('leave_balance_transactions', [
            'leave_application_id' => $application->id,
            'transaction_type' => 'deduction',
        ]);
    }

    public function test_inactive_leave_type_cannot_have_balance_deducted(): void
    {
        $balance = $this->createBalance(
            remainingDays: '5.00',
            periodStart: '2026-01-15',
            periodEnd: '2027-01-14',
        );

        $this->cl->update([
            'is_active' => false,
        ]);

        $application = $this->createLeaveApplication(
            from: '2026-10-26',
            to: '2026-10-26',
            requestedDays: '1.00',
            duration: $this->fullDay,
        );

        try {
            $this->deductionService->deduct(
                $application,
                $this->actor
            );

            $this->fail('An inactive leave type must not be deducted.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString(
                'requested leave type is inactive',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseHas('leavebalances', [
            'id' => $balance->id,
            'remaining_days' => '5.00',
        ]);
    }

    public function test_lwp_cannot_use_paid_leave_balance_deduction(): void
    {
        $lwp = LeaveType::create([
            'code' => 'LWP',
            'name' => 'Leave Without Pay',
            'is_active' => true,
        ]);

        $application = $this->createLeaveApplication(
            from: '2026-10-26',
            to: '2026-10-26',
            requestedDays: '1.00',
            duration: $this->fullDay,
            leaveType: $lwp,
        );

        try {
            $this->deductionService->deduct(
                $application,
                $this->actor
            );

            $this->fail('LWP must not use paid leave deduction.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString(
                'LWP must be processed',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseMissing('leave_balance_transactions', [
            'leave_application_id' => $application->id,
            'transaction_type' => 'deduction',
        ]);
    }

    public function test_multiple_days_request_excludes_weekend_dates(): void
    {
        $balance = $this->createBalance(
            remainingDays: '3.00',
            periodStart: '2026-01-15',
            periodEnd: '2027-01-14',
        );

        $application = $this->createLeaveApplication(
            from: '2026-10-23',
            to: '2026-10-26',
            requestedDays: '2.00',
            duration: $this->multipleDays,
        );

        $results = $this->deductionService->deduct(
            $application,
            $this->actor
        );

        $this->assertCount(1, $results);
        $this->assertSame('2.00', $results[0]['deducted_days']);
        $this->assertSame('1.00', $results[0]['remaining_days']);

        $this->assertDatabaseHas('leavebalances', [
            'id' => $balance->id,
            'remaining_days' => '1.00',
        ]);
    }

    private function createLeaveApplication(
        string $from,
        string $to,
        string $requestedDays,
        Duration $duration,
        ?string $halfDayPeriod = null,
        string $status = 'approved',
        ?LeaveType $leaveType = null,
    ): LeaveApplication {
        return LeaveApplication::create([
            'employee_id' => $this->employee->id,
            'leave_type_id' => ($leaveType ?? $this->cl)->id,
            'duration_id' => $duration->id,
            'half_day_period' => $halfDayPeriod,
            'leave_from_date' => $from,
            'leave_to_date' => $to,
            'leavedeductcount' => $requestedDays,
            'leavereason' => 'Test leave application',
            'status' => $status,
        ]);
    }

    private function createBalance(
        string $remainingDays,
        string $periodStart,
        string $periodEnd,
        ?string $expiresAt = null,
        ?string $grantKey = null,
    ): LeaveBalance {
        return LeaveBalance::create([
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->cl->id,
            'allocation_type' => 'annual',
            'allocated_days' => $remainingDays,
            'remaining_days' => $remainingDays,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'expires_at' => $expiresAt ?? $periodEnd,
            'source_balance_id' => null,
            'grant_key' => $grantKey ?? uniqid('test:CL:', true),
        ]);
    }

    public function test_multiple_days_leave_excludes_configured_holidays(): void
    {
        $balance = $this->createBalance(
            remainingDays: '3.00',
            periodStart: '2026-01-15',
            periodEnd: '2027-01-14',
        );

        Holiday::create([
            'holiday_date' => '2026-10-26',
            'name' => 'Test Public Holiday',
            'description' => 'Holiday created for deduction testing.',
        ]);

        // Friday, 23 October, is a working day.
        // Saturday and Sunday are weekends.
        // Monday, 26 October, is a configured holiday.
        // Tuesday, 27 October, is a working day.
        $application = $this->createLeaveApplication(
            from: '2026-10-23',
            to: '2026-10-27',
            requestedDays: '2.00',
            duration: $this->multipleDays,
        );

        $results = $this->deductionService->deduct(
            $application,
            $this->actor
        );

        $this->assertCount(1, $results);
        $this->assertSame('2.00', $results[0]['deducted_days']);
        $this->assertSame('1.00', $results[0]['remaining_days']);

        $this->assertDatabaseHas('leavebalances', [
            'id' => $balance->id,
            'remaining_days' => '1.00',
        ]);

        $this->assertDatabaseHas('leave_balance_transactions', [
            'leave_balance_id' => $balance->id,
            'leave_application_id' => $application->id,
            'transaction_type' => 'deduction',
            'days' => '2.00',
            'balance_after' => '1.00',
        ]);
    }

    public function test_full_day_request_spanning_multiple_dates_is_rejected(): void
    {
        $balance = $this->createBalance(
            remainingDays: '3.00',
            periodStart: '2026-01-15',
            periodEnd: '2027-01-14',
        );

        $application = $this->createLeaveApplication(
            from: '2026-10-26',
            to: '2026-10-27',
            requestedDays: '2.00',
            duration: $this->fullDay,
        );

        try {
            $this->deductionService->deduct(
                $application,
                $this->actor
            );

            $this->fail(
                'A Full Day request must not span multiple dates.'
            );
        } catch (ValidationException $exception) {
            $this->assertStringContainsString(
                'A Full Day request must cover exactly one date.',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseHas('leavebalances', [
            'id' => $balance->id,
            'remaining_days' => '3.00',
        ]);

        $this->assertDatabaseMissing('leave_balance_transactions', [
            'leave_application_id' => $application->id,
            'transaction_type' => 'deduction',
        ]);
    }
}
