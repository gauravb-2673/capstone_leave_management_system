<?php

namespace App\Services\Leave;

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveBalanceTransaction;
use App\Models\LeaveType;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LeaveAllocationService
{
    public function __construct(
        private LeaveEntitlementService $entitlementService
    ) {}

    /**
     * Calculate and save eligible annual leave allocations.
     *
     * Existing allocations are reused rather than duplicated.
     */
    public function allocate(Employee $employee, CarbonInterface|string $date): array
    {
        // Calculate which entitlements the employee qualifies for.
        $entitlements = $this->entitlementService->calculate($employee, $date);

        if ($entitlements === []) {
            return [];
        }

        return DB::transaction(function () use ($employee, $entitlements) {
            // Lock and reload the employee so we check the current database state.
            $lockedEmployee = Employee::query()
                ->lockForUpdate()
                ->findOrFail($employee->id);

            // An inactive employee must not receive new allocations.
            if (! $lockedEmployee->is_active) {
                return [];
            }

            $leaveTypes = LeaveType::query()
                ->whereIn('code', array_column($entitlements, 'code'))
                ->where('is_active', true)
                ->get()
                ->keyBy('code');

            $results = [];

            foreach ($entitlements as $entitlement) {
                $leaveType = $leaveTypes->get($entitlement['code']);

                if (! $leaveType) {
                    throw new RuntimeException(
                        "Active leave type {$entitlement['code']} was not found."
                    );
                }

                // Find an existing allocation or create a new one.
                $balance = LeaveBalance::firstOrCreate(
                    ['grant_key' => $entitlement['grant_key']],
                    [
                        'employee_id' => $lockedEmployee->id,
                        'leave_type_id' => $leaveType->id,
                        'allocation_type' => $entitlement['allocation_type'],
                        'allocated_days' => $entitlement['allocated_days'],
                        'remaining_days' => $entitlement['allocated_days'],
                        'period_start' => $entitlement['period_start'],
                        'period_end' => $entitlement['period_end'],
                        'expires_at' => $entitlement['period_end'],
                        'source_balance_id' => null,
                    ]
                );

                // Only newly created allocations receive an initial ledger entry.
                // Re-running the allocator must not create duplicate entries.
                if ($balance->wasRecentlyCreated) {
                    LeaveBalanceTransaction::create([
                        'leave_balance_id' => $balance->id,
                        'leave_application_id' => null,
                        'reverses_transaction_id' => null,
                        'transaction_type' => 'allocation',
                        'days' => $balance->allocated_days,
                        'balance_after' => $balance->remaining_days,
                        'description' => sprintf(
                            'Annual %s allocation for service period %s to %s.',
                            $entitlement['code'],
                            $entitlement['period_start'],
                            $entitlement['period_end']
                        ),
                        'created_by' => null,
                    ]);
                }

                $results[] = [
                    'code' => $entitlement['code'],
                    'balance_id' => $balance->id,
                    'allocated_days' => $balance->allocated_days,
                    'remaining_days' => $balance->remaining_days,
                    'created' => $balance->wasRecentlyCreated,
                ];
            }

            return $results;
        });
    }
}
