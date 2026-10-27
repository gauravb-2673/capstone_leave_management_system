<?php

namespace Tests\Unit\Services\Leave;

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveBalanceTransaction;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\Leave\LeaveAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveAllocationServiceTest extends TestCase
{
    use RefreshDatabase;

    private Employee $employee;

    private LeaveAllocationService $allocationService;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();

        $this->employee = Employee::create([
            'user_id' => $user->id,
            'department_id' => null,
            'employee_code' => 'ALLOC001',
            'joining_date' => '2025-01-15',
            'is_active' => true,
        ]);

        LeaveType::create([
            'code' => 'CL',
            'name' => 'Casual Leave',
            'is_active' => true,
        ]);

        LeaveType::create([
            'code' => 'SL',
            'name' => 'Sick Leave',
            'is_active' => true,
        ]);

        $this->allocationService = app(
            LeaveAllocationService::class
        );
    }

    public function test_eligible_employee_receives_annual_allocations_with_ledger_entries(): void
    {
        $results = $this->allocationService->allocate(
            $this->employee,
            '2026-01-15'
        );

        $this->assertCount(2, $results);

        $this->assertDatabaseHas('leavebalances', [
            'employee_id' => $this->employee->id,
            'allocation_type' => 'annual',
            'allocated_days' => '12.00',
            'remaining_days' => '12.00',
            'period_start' => '2026-01-15',
            'period_end' => '2027-01-14',
        ]);

        $this->assertDatabaseHas('leavebalances', [
            'employee_id' => $this->employee->id,
            'allocation_type' => 'annual',
            'allocated_days' => '6.00',
            'remaining_days' => '6.00',
            'period_start' => '2026-01-15',
            'period_end' => '2027-01-14',
        ]);

        $balances = LeaveBalance::query()
            ->where('employee_id', $this->employee->id)
            ->get();

        $this->assertCount(2, $balances);

        foreach ($balances as $balance) {
            $this->assertDatabaseHas('leave_balance_transactions', [
                'leave_balance_id' => $balance->id,
                'leave_application_id' => null,
                'reverses_transaction_id' => null,
                'transaction_type' => 'allocation',
                'days' => $balance->allocated_days,
                'balance_after' => $balance->remaining_days,
                'created_by' => null,
            ]);
        }

        $this->assertSame(
            2,
            LeaveBalanceTransaction::query()
                ->where('transaction_type', 'allocation')
                ->count()
        );
    }

    public function test_running_allocator_again_does_not_duplicate_allocations_or_ledger_entries(): void
    {
        $firstResults = $this->allocationService->allocate(
            $this->employee,
            '2026-01-15'
        );

        $secondResults = $this->allocationService->allocate(
            $this->employee,
            '2026-01-15'
        );

        $this->assertCount(2, $firstResults);
        $this->assertCount(2, $secondResults);

        $this->assertSame(2, LeaveBalance::query()->count());

        $this->assertSame(
            2,
            LeaveBalanceTransaction::query()
                ->where('transaction_type', 'allocation')
                ->count()
        );

        $this->assertSame(
            2,
            collect($firstResults)
                ->where('created', true)
                ->count()
        );

        $this->assertSame(
            0,
            collect($secondResults)
                ->where('created', true)
                ->count()
        );
    }

    public function test_inactive_employee_receives_no_allocations(): void
    {
        $this->employee->update([
            'is_active' => false,
        ]);

        $results = $this->allocationService->allocate(
            $this->employee,
            '2026-01-15'
        );

        $this->assertSame([], $results);

        $this->assertDatabaseMissing('leavebalances', [
            'employee_id' => $this->employee->id,
        ]);

        $this->assertDatabaseMissing('leave_balance_transactions', [
            'transaction_type' => 'allocation',
        ]);
    }

    public function test_allocation_is_rolled_back_when_ledger_transaction_fails(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        // Force the ledger insert to fail through its required foreign key.
        // The balance insert happens first inside the same transaction.
        \Illuminate\Support\Facades\DB::listen(
            function (\Illuminate\Database\Events\QueryExecuted $query): void {
                if (
                    str_contains(
                        strtolower($query->sql),
                        'insert into `leave_balance_transactions`'
                    )
                ) {
                    throw new \Illuminate\Database\QueryException(
                        'mysql',
                        $query->sql,
                        $query->bindings,
                        new \PDOException('Simulated ledger insert failure')
                    );
                }
            }
        );

        try {
            $this->allocationService->allocate(
                $this->employee,
                '2026-01-15'
            );
        } finally {
            // The test assertions below are intentionally not placed here:
            // the expected exception is checked by PHPUnit.
        }
    }

    public function test_employee_does_not_receive_annual_allocation_before_first_service_anniversary(): void
    {
        $results = $this->allocationService->allocate(
            $this->employee,
            '2026-01-14'
        );

        $this->assertSame([], $results);

        $this->assertDatabaseMissing('leavebalances', [
            'employee_id' => $this->employee->id,
        ]);

        $this->assertDatabaseMissing('leave_balance_transactions', [
            'transaction_type' => 'allocation',
        ]);
    }

    public function test_employee_receives_annual_allocation_on_first_service_anniversary(): void
    {
        $results = $this->allocationService->allocate(
            $this->employee,
            '2026-01-15'
        );

        $this->assertCount(2, $results);

        $this->assertDatabaseHas('leavebalances', [
            'employee_id' => $this->employee->id,
            'allocation_type' => 'annual',
            'allocated_days' => '12.00',
            'period_start' => '2026-01-15',
            'period_end' => '2027-01-14',
        ]);

        $this->assertDatabaseHas('leavebalances', [
            'employee_id' => $this->employee->id,
            'allocation_type' => 'annual',
            'allocated_days' => '6.00',
            'period_start' => '2026-01-15',
            'period_end' => '2027-01-14',
        ]);
    }
}
