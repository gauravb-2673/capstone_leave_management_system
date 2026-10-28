<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Services\Leave\LeaveAllocationService;
use App\Services\Leave\LeaveEntitlementService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Throwable;

class AllocateLeaveBalances extends Command
{
    /**
     * Command name and supported options.
     *
     * Examples:
     * php artisan leave:allocate-balances
     * php artisan leave:allocate-balances --date=2026-10-28
     * php artisan leave:allocate-balances --dry-run
     */
    protected $signature = 'leave:allocate-balances
                            {--date= : Date to calculate leave eligibility for (YYYY-MM-DD)}
                            {--dry-run : Preview eligible allocations without saving changes}';

    /**
     * Description shown by php artisan list.
     */
    protected $description = 'Allocate annual leave balances and eligible CL carry-forward';

    /**
     * Execute the command.
     */
    public function handle(
        LeaveAllocationService $allocationService,
        LeaveEntitlementService $entitlementService
    ): int {
        /*
         * STEP 1: Determine the date to process.
         *
         * If --date is omitted, use today's date.
         */
        $dateOption = $this->option('date');

        try {
            if ($dateOption) {
                $date = Carbon::createFromFormat('!Y-m-d', $dateOption);

                // Reject invalid dates and incorrectly formatted dates.
                if (
                    ! $date ||
                    $date->format('Y-m-d') !== $dateOption
                ) {
                    $this->error(
                        'Invalid date. Use the YYYY-MM-DD format, for example 2026-10-28.'
                    );

                    return self::FAILURE;
                }
            } else {
                $date = today()->startOfDay();
            }
        } catch (Throwable $exception) {
            $this->error(
                'Invalid date. Use the YYYY-MM-DD format, for example 2026-10-28.'
            );

            return self::FAILURE;
        }

        $dateString = $date->toDateString();
        $dryRun = (bool) $this->option('dry-run');

        $this->info("Processing leave allocations for: {$dateString}");

        if ($dryRun) {
            $this->warn('DRY RUN: No database changes will be made.');
        }

        /*
         * STEP 2: Find active employees who have joined by the
         * selected date.
         *
         * Employees who joined after this date must not receive
         * allocations for it.
         */
        $employees = Employee::query()
            ->where('is_active', true)
            ->whereDate('joining_date', '<=', $dateString)
            ->orderBy('id')
            ->get();

        if ($employees->isEmpty()) {
            $this->info(
                'No active employees were found who had joined by this date.'
            );

            return self::SUCCESS;
        }

        /*
         * STEP 3: Process each employee.
         */
        $eligibleEmployees = 0;
        $skippedEmployees = 0;
        $createdAllocations = 0;
        $reusedAllocations = 0;
        $failedEmployees = 0;

        foreach ($employees as $employee) {
            try {
                /*
                 * Calculate eligibility first.
                 *
                 * This method does not create or modify database records.
                 */
                $entitlements = $entitlementService->calculate(
                    $employee,
                    $date
                );

                if ($entitlements === []) {
                    $skippedEmployees++;

                    $this->line(
                        "Employee #{$employee->id}: no eligible annual allocations."
                    );

                    continue;
                }

                $eligibleEmployees++;

                /*
                 * DRY RUN:
                 * Display the proposed annual allocations and do not
                 * call the method that writes to the database.
                 */
                if ($dryRun) {
                    $this->line(
                        "Employee #{$employee->id}: eligible for allocation."
                    );

                    foreach ($entitlements as $entitlement) {
                        $this->line(sprintf(
                            '  %s: %s days (%s to %s)',
                            $entitlement['code'],
                            number_format(
                                (float) $entitlement['allocated_days'],
                                2
                            ),
                            $entitlement['period_start'],
                            $entitlement['period_end']
                        ));
                    }

                    continue;
                }

                /*
                 * LIVE RUN:
                 * Create or reuse annual allocations and process
                 * eligible CL carry-forward.
                 */
                $results = $allocationService->allocate(
                    $employee,
                    $date
                );

                foreach ($results as $result) {
                    if ($result['created']) {
                        $createdAllocations++;
                    } else {
                        $reusedAllocations++;
                    }

                    $action = $result['created']
                        ? 'created'
                        : 'reused';

                    $this->line(sprintf(
                        'Employee #%d: %s %s %s-day allocation (balance ID: %d).',
                        $employee->id,
                        $action,
                        $result['code'],
                        number_format(
                            (float) $result['allocated_days'],
                            2
                        ),
                        $result['balance_id']
                    ));
                }
            } catch (Throwable $exception) {
                $failedEmployees++;

                $this->error(
                    "Employee #{$employee->id}: allocation failed. "
                        . $exception->getMessage()
                );
            }
        }

        /*
         * STEP 4: Display a summary.
         */
        $this->newLine();
        $this->info('Leave allocation summary');
        $this->line("Date processed: {$dateString}");
        $this->line("Employees checked: {$employees->count()}");
        $this->line("Employees eligible: {$eligibleEmployees}");
        $this->line("Employees skipped: {$skippedEmployees}");

        if (! $dryRun) {
            $this->line("Allocations created: {$createdAllocations}");
            $this->line("Allocations reused: {$reusedAllocations}");
        }

        $this->line("Employees with errors: {$failedEmployees}");

        if ($dryRun) {
            $this->warn(
                'Preview only. Run without --dry-run to save eligible allocations.'
            );
        }

        return $failedEmployees > 0
            ? self::FAILURE
            : self::SUCCESS;
    }
}
