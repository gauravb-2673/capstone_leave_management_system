<?php

namespace Tests\Unit\Services\Leave;

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\Leave\LeaveBalanceEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveBalanceEligibilityServiceTest extends TestCase
{
    use RefreshDatabase;

    private Employee $employee;

    private LeaveType $cl;

    private LeaveBalanceEligibilityService $eligibilityService;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();

        $this->employee = Employee::create([
            'user_id' => $user->id,
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

        $this->eligibilityService = app(
            LeaveBalanceEligibilityService::class
        );
    }

    public function test_request_is_eligible_when_sufficient_balance_exists(): void
    {
        $this->createBalance(
            remainingDays: '3.00',
            periodStart: '2026-01-15',
            periodEnd: '2027-01-14',
        );

        $result = $this->eligibilityService->check(
            $this->employee,
            'CL',
            '2026-10-26',
            '2026-10-27',
            2.00
        );

        $this->assertTrue($result['eligible']);
        $this->assertSame('2.00', $result['requested_days']);
    }

    public function test_request_is_rejected_when_balance_is_insufficient(): void
    {
        $this->createBalance(
            remainingDays: '1.00',
            periodStart: '2026-01-15',
            periodEnd: '2027-01-14',
        );

        $result = $this->eligibilityService->check(
            $this->employee,
            'CL',
            '2026-10-26',
            '2026-10-27',
            2.00
        );

        $this->assertFalse($result['eligible']);
        $this->assertStringContainsString(
            'Insufficient leave balance',
            $result['message']
        );
    }

    public function test_inactive_leave_type_is_rejected(): void
    {
        $this->cl->update([
            'is_active' => false,
        ]);

        $result = $this->eligibilityService->check(
            $this->employee,
            'CL',
            '2026-10-26',
            '2026-10-26',
            1.00
        );

        $this->assertFalse($result['eligible']);
        $this->assertSame(
            'The requested leave type is missing or inactive.',
            $result['message']
        );
    }

    public function test_leave_before_joining_date_is_rejected(): void
    {
        $result = $this->eligibilityService->check(
            $this->employee,
            'CL',
            '2025-01-14',
            '2025-01-14',
            1.00
        );

        $this->assertFalse($result['eligible']);
        $this->assertSame(
            'Leave cannot start before the employee joining date.',
            $result['message']
        );
    }

    public function test_expired_allocation_cannot_cover_a_leave_date(): void
    {
        $this->createBalance(
            remainingDays: '5.00',
            periodStart: '2026-01-15',
            periodEnd: '2027-01-14',
            expiresAt: '2026-10-25',
        );

        $result = $this->eligibilityService->check(
            $this->employee,
            'CL',
            '2026-10-26',
            '2026-10-26',
            1.00
        );

        $this->assertFalse($result['eligible']);
        $this->assertStringContainsString(
            'Insufficient leave balance',
            $result['message']
        );
    }

    public function test_request_crossing_service_year_boundary_uses_both_periods(): void
    {
        $firstPeriodBalance = $this->createBalance(
            remainingDays: '1.00',
            periodStart: '2026-01-15',
            periodEnd: '2027-01-14',
            grantKey: 'test:CL:first-period',
        );

        $secondPeriodBalance = $this->createBalance(
            remainingDays: '1.00',
            periodStart: '2027-01-15',
            periodEnd: '2028-01-14',
            grantKey: 'test:CL:second-period',
        );

        $result = $this->eligibilityService->check(
            $this->employee,
            'CL',
            '2027-01-14',
            '2027-01-15',
            2.00
        );

        $this->assertTrue($result['eligible']);

        // Eligibility checks must not deduct from either allocation.
        $this->assertDatabaseHas('leavebalances', [
            'id' => $firstPeriodBalance->id,
            'remaining_days' => '1.00',
        ]);

        $this->assertDatabaseHas('leavebalances', [
            'id' => $secondPeriodBalance->id,
            'remaining_days' => '1.00',
        ]);
    }

    public function test_half_day_request_needs_only_half_a_day(): void
    {
        $this->createBalance(
            remainingDays: '0.50',
            periodStart: '2026-01-15',
            periodEnd: '2027-01-14',
        );

        $result = $this->eligibilityService->check(
            $this->employee,
            'CL',
            '2026-10-26',
            '2026-10-26',
            0.50
        );

        $this->assertTrue($result['eligible']);
        $this->assertSame('0.50', $result['requested_days']);
    }

    public function test_eligibility_check_does_not_change_stored_balance(): void
    {
        $balance = $this->createBalance(
            remainingDays: '4.00',
            periodStart: '2026-01-15',
            periodEnd: '2027-01-14',
        );

        $result = $this->eligibilityService->check(
            $this->employee,
            'CL',
            '2026-10-26',
            '2026-10-27',
            2.00
        );

        $this->assertTrue($result['eligible']);

        $this->assertDatabaseHas('leavebalances', [
            'id' => $balance->id,
            'remaining_days' => '4.00',
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
}
