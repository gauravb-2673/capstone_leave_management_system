<?php

namespace Tests\Unit\Services\Leave;

use App\Models\Duration;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveApplication;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\Leave\LwpEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LwpEligibilityServiceTest extends TestCase
{
    use RefreshDatabase;

    private Employee $employee;

    private LeaveType $lwp;

    private Duration $fullDay;

    private Duration $multipleDays;

    private Duration $halfDay;

    private LwpEligibilityService $eligibilityService;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();

        $this->employee = Employee::create([
            'user_id' => $user->id,
            'department_id' => null,
            'employee_code' => 'LWP-'.uniqid(),
            'joining_date' => '2025-01-15',
            'is_active' => true,
        ]);

        $this->lwp = LeaveType::create([
            'code' => 'LWP',
            'name' => 'Leave Without Pay',
            'is_active' => true,
        ]);

        $this->fullDay = Duration::create([
            'name' => 'Full Day',
            'days' => 1.00,
        ]);

        $this->multipleDays = Duration::create([
            'name' => 'Multiple Days',
            'days' => 1.00,
        ]);

        $this->halfDay = Duration::create([
            'name' => 'Half Day',
            'days' => 0.50,
        ]);

        $this->eligibilityService = app(
            LwpEligibilityService::class
        );
    }

    public function test_request_is_eligible_when_total_stays_within_annual_limit(): void
    {
        // October 1–15, 2026 contains 11 weekdays.
        $this->createLeaveApplication(
            fromDate: '2026-10-01',
            toDate: '2026-10-15',
            status: 'approved',
            deduction: '11.00',
        );

        $result = $this->eligibilityService->check(
            $this->employee,
            '2026-10-26',
            '2026-10-26',
            1.00
        );

        $this->assertTrue($result['eligible']);
        $this->assertSame('1.00', $result['requested_days']);
        $this->assertSame('12.00', $result['annual_limit']);
    }

    public function test_request_is_rejected_when_it_exceeds_annual_limit(): void
    {
        // October 1–16, 2026 contains 12 weekdays.
        $this->createLeaveApplication(
            fromDate: '2026-10-01',
            toDate: '2026-10-16',
            status: 'approved',
            deduction: '12.00',
        );

        $result = $this->eligibilityService->check(
            $this->employee,
            '2026-10-19',
            '2026-10-19',
            1.00
        );

        $this->assertFalse($result['eligible']);

        $this->assertStringContainsString(
            'LWP limit of 12 days',
            $result['message']
        );

        $this->assertSame('12.00', $result['used_days']);
        $this->assertSame('1.00', $result['requested_days']);
    }

    public function test_pending_rejected_and_cancelled_applications_do_not_count(): void
    {
        foreach (['pending', 'rejected', 'cancelled'] as $status) {
            $this->createLeaveApplication(
                fromDate: '2026-10-01',
                toDate: '2026-10-16',
                status: $status,
                deduction: '12.00',
            );
        }

        $result = $this->eligibilityService->check(
            $this->employee,
            '2026-10-19',
            '2026-10-19',
            1.00
        );

        $this->assertTrue($result['eligible']);
    }

    public function test_approved_half_day_counts_as_half_a_day(): void
    {
        $this->createLeaveApplication(
            fromDate: '2026-10-01',
            toDate: '2026-10-01',
            status: 'approved',
            deduction: '0.50',
            duration: $this->halfDay,
        );

        $result = $this->eligibilityService->check(
            $this->employee,
            '2026-10-02',
            '2026-10-02',
            0.50
        );

        $this->assertTrue($result['eligible']);
        $this->assertSame('0.50', $result['requested_days']);
    }

    public function test_half_day_request_cannot_span_multiple_calendar_dates(): void
    {
        // October 27 is a holiday. October 24–27 spans several
        // calendar dates but contains only one working date.
        Holiday::create([
            'holiday_date' => '2026-10-27',
            'name' => 'Test Holiday',
            'description' => null,
        ]);

        $result = $this->eligibilityService->check(
            $this->employee,
            '2026-10-24',
            '2026-10-27',
            0.50
        );

        $this->assertFalse($result['eligible']);

        $this->assertSame(
            'A half-day request must cover exactly one date.',
            $result['message']
        );
    }

    public function test_inactive_employee_cannot_request_lwp(): void
    {
        $this->employee->update([
            'is_active' => false,
        ]);

        $result = $this->eligibilityService->check(
            $this->employee,
            '2026-10-26',
            '2026-10-26',
            1.00
        );

        $this->assertFalse($result['eligible']);

        $this->assertSame(
            'Inactive employees cannot request leave.',
            $result['message']
        );
    }

    public function test_lwp_type_must_be_active(): void
    {
        $this->lwp->update([
            'is_active' => false,
        ]);

        $result = $this->eligibilityService->check(
            $this->employee,
            '2026-10-26',
            '2026-10-26',
            1.00
        );

        $this->assertFalse($result['eligible']);

        $this->assertSame(
            'The LWP leave type is missing or inactive.',
            $result['message']
        );
    }

    public function test_weekends_and_holidays_are_excluded_from_requested_days(): void
    {
        Holiday::create([
            'holiday_date' => '2026-10-27',
            'name' => 'Test Holiday',
            'description' => null,
        ]);

        // Monday October 26 is a working day.
        // Tuesday October 27 is a holiday.
        $result = $this->eligibilityService->check(
            $this->employee,
            '2026-10-26',
            '2026-10-27',
            1.00
        );

        $this->assertTrue($result['eligible']);
        $this->assertSame('1.00', $result['requested_days']);
    }

    private function createLeaveApplication(
        string $fromDate,
        string $toDate,
        string $status,
        string $deduction,
        ?Duration $duration = null,
    ): LeaveApplication {
        return LeaveApplication::create([
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->lwp->id,
            'duration_id' => (
                $duration ?? $this->multipleDays
            )->id,
            'half_day_period' => $duration === $this->halfDay
                ? 'first_half'
                : null,
            'leave_from_date' => $fromDate,
            'leave_to_date' => $toDate,
            'leavedeductcount' => $deduction,
            'leavereason' => 'Automated test application',
            'status' => $status,
        ]);
    }
}
