<?php

namespace App\Services\Leave;

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveBalanceTransaction;
use App\Models\LeaveType;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LeaveAllocationService
{
    /**
     * Maximum unused Casual Leave days that can be carried forward.
     */
    private const CL_CARRY_FORWARD_LIMIT = 5.00;

    public function __construct(
        private LeaveEntitlementService $entitlementService
    ) {}

    /**
     * Calculate and save eligible annual leave allocations.
     *
     * Existing allocations are reused rather than duplicated.
     * Eligible unused CL may be carried forward from the previous
     * service year.
     */
    public function allocate(
        Employee $employee,
        CarbonInterface|string $date
    ): array {
        // Calculate the annual entitlements for the supplied date.
        $entitlements = $this->entitlementService->calculate(
            $employee,
            $date
        );

        // No eligible entitlements means there is nothing to allocate.
        if ($entitlements === []) {
            return [];
        }

        return DB::transaction(function () use (
            $employee,
            $entitlements
        ) {
            /*
             * Lock and reload the employee.
             *
             * This helps serialize allocation attempts for the same
             * employee when the database supports row-level locking.
             */
            $lockedEmployee = Employee::query()
                ->whereKey($employee->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Inactive employees must not receive new allocations.
            if (! $lockedEmployee->is_active) {
                return [];
            }

            /*
             * Load the active leave types required by the entitlements.
             */
            $leaveTypes = LeaveType::query()
                ->whereIn(
                    'code',
                    array_column($entitlements, 'code')
                )
                ->where('is_active', true)
                ->get()
                ->keyBy('code');

            $results = [];

            /*
             * STEP 1:
             * Create or reuse the annual allocations first.
             */
            foreach ($entitlements as $entitlement) {
                $leaveType = $leaveTypes->get(
                    $entitlement['code']
                );

                if (! $leaveType) {
                    throw new RuntimeException(
                        "Active leave type {$entitlement['code']} was not found."
                    );
                }

                /*
                 * grant_key uniquely identifies this employee's
                 * allocation for this leave type and service year.
                 */
                $balance = LeaveBalance::firstOrCreate(
                    [
                        'grant_key' => $entitlement['grant_key'],
                    ],
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

                /*
                 * Record the initial allocation only when a new
                 * balance record has actually been created.
                 */
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
                    'allocation_type' => $entitlement['allocation_type'],
                    'balance_id' => $balance->id,
                    'allocated_days' => $balance->allocated_days,
                    'remaining_days' => $balance->remaining_days,
                    'created' => $balance->wasRecentlyCreated,
                ];
            }

            /*
             * STEP 2:
             * Carry forward unused CL from the immediately preceding
             * service-year allocation.
             *
             * SL is not eligible for carry-forward.
             */
            $clEntitlement = collect($entitlements)
                ->firstWhere('code', 'CL');

            $clType = $leaveTypes->get('CL');

            if ($clEntitlement && $clType) {
                $carryForwardResult = $this->carryForwardCasualLeave(
                    $lockedEmployee,
                    $clType,
                    $clEntitlement
                );

                if ($carryForwardResult !== null) {
                    $results[] = $carryForwardResult;
                }
            }

            return $results;
        });
    }

    /**
     * Carry forward unused CL from the immediately preceding
     * annual allocation.
     *
     * At most five days are transferred. Any unused amount above
     * that limit expires.
     */
    private function carryForwardCasualLeave(
        Employee $employee,
        LeaveType $leaveType,
        array $entitlement
    ): ?array {
        $currentPeriodStart = Carbon::parse(
            $entitlement['period_start']
        )->startOfDay();

        $currentPeriodEnd = Carbon::parse(
            $entitlement['period_end']
        )->startOfDay();

        /*
         * The previous service period must end one day before
         * the current service period starts.
         */
        $previousPeriodEnd = $currentPeriodStart
            ->copy()
            ->subDay()
            ->toDateString();

        /*
         * Use a separate grant key for carry-forward.
         *
         * This prevents the carry-forward record from being
         * confused with the regular annual CL allocation.
         */
        $grantKey = sprintf(
            'employee:%d:CL:carry_forward:%s',
            $employee->id,
            $currentPeriodStart->toDateString()
        );

        /*
         * Idempotency check:
         * if this service year's carry-forward already exists,
         * do not transfer the same source balance again.
         */
        $existingCarryForward = LeaveBalance::query()
            ->where('grant_key', $grantKey)
            ->first();

        if ($existingCarryForward) {
            return [
                'code' => 'CL',
                'allocation_type' => 'carry_forward',
                'balance_id' => $existingCarryForward->id,
                'allocated_days' => $existingCarryForward->allocated_days,
                'remaining_days' => $existingCarryForward->remaining_days,
                'created' => false,
            ];
        }

        /*
         * Find only the immediately preceding annual CL allocation.
         *
         * Do not create missing historical allocations here.
         */
        $sourceBalance = LeaveBalance::query()
            ->where('employee_id', $employee->id)
            ->where('leave_type_id', $leaveType->id)
            ->where('allocation_type', 'annual')
            ->whereDate('period_end', $previousPeriodEnd)
            ->lockForUpdate()
            ->first();

        // There is nothing to carry forward if no source exists.
        if (! $sourceBalance) {
            return null;
        }

        /*
         * Convert decimal days into integer hundredths to avoid
         * floating-point arithmetic when calculating the transfer.
         *
         * Example: 4.50 days becomes 450 hundredths.
         */
        $sourceRemaining = (int) round(
            (float) $sourceBalance->remaining_days * 100
        );

        if ($sourceRemaining <= 0) {
            return null;
        }

        $carryForwardLimit = (int) round(
            self::CL_CARRY_FORWARD_LIMIT * 100
        );

        // Transfer no more than five unused days.
        $carryForwardHundredths = min(
            $sourceRemaining,
            $carryForwardLimit
        );

        // Any unused amount above the limit expires.
        $expiredHundredths = $sourceRemaining
            - $carryForwardHundredths;

        $carryForwardDays = number_format(
            $carryForwardHundredths / 100,
            2,
            '.',
            ''
        );

        $expiredDays = number_format(
            $expiredHundredths / 100,
            2,
            '.',
            ''
        );

        /*
         * STEP 3:
         * Create the new carry-forward balance.
         *
         * source_balance_id links this record to the old allocation,
         * allowing the transfer to be traced.
         */
        $carryForward = LeaveBalance::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'allocation_type' => 'carry_forward',
            'allocated_days' => $carryForwardDays,
            'remaining_days' => $carryForwardDays,
            'period_start' => $currentPeriodStart->toDateString(),
            'period_end' => $currentPeriodEnd->toDateString(),
            'expires_at' => $currentPeriodEnd->toDateString(),
            'source_balance_id' => $sourceBalance->id,
            'grant_key' => $grantKey,
        ]);

        /*
         * STEP 4:
         * Remove the old allocation's entire unused remainder.
         *
         * The permitted amount has moved to the new balance.
         * The excess, if any, has expired.
         *
         * This update and all ledger entries are inside the same
         * database transaction.
         */
        $sourceBalance->remaining_days = '0.00';
        $sourceBalance->save();

        /*
         * STEP 5:
         * Record the amount transferred out of the old allocation.
         *
         * After the transfer, the old allocation's remaining amount
         * is the amount that will expire.
         */
        LeaveBalanceTransaction::create([
            'leave_balance_id' => $sourceBalance->id,
            'leave_application_id' => null,
            'reverses_transaction_id' => null,
            'transaction_type' => 'carry_forward_out',
            'days' => $carryForwardDays,
            'balance_after' => $expiredDays,
            'description' => sprintf(
                'Transferred %s unused CL days to carry-forward allocation %d.',
                $carryForwardDays,
                $carryForward->id
            ),
            'created_by' => null,
        ]);

        /*
         * STEP 6:
         * If more than five days remained, record the excess as
         * expired. Otherwise, this transaction is not necessary.
         */
        if ($expiredHundredths > 0) {
            LeaveBalanceTransaction::create([
                'leave_balance_id' => $sourceBalance->id,
                'leave_application_id' => null,
                'reverses_transaction_id' => null,
                'transaction_type' => 'expiry',
                'days' => $expiredDays,
                'balance_after' => '0.00',
                'description' => sprintf(
                    '%s unused CL days expired because they exceeded the carry-forward limit.',
                    $expiredDays
                ),
                'created_by' => null,
            ]);
        }

        /*
         * STEP 7:
         * Record the incoming amount on the new allocation.
         */
        LeaveBalanceTransaction::create([
            'leave_balance_id' => $carryForward->id,
            'leave_application_id' => null,
            'reverses_transaction_id' => null,
            'transaction_type' => 'carry_forward',
            'days' => $carryForwardDays,
            'balance_after' => $carryForwardDays,
            'description' => sprintf(
                'CL carried forward from allocation %d.',
                $sourceBalance->id
            ),
            'created_by' => null,
        ]);

        return [
            'code' => 'CL',
            'allocation_type' => 'carry_forward',
            'balance_id' => $carryForward->id,
            'allocated_days' => $carryForward->allocated_days,
            'remaining_days' => $carryForward->remaining_days,
            'created' => true,
        ];
    }
}
