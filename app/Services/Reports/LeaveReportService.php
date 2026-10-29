<?php

namespace App\Services\Reports;

use App\Models\LeaveApplication;
use App\Models\LeaveBalance;
use App\Models\LeaveBalanceTransaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;

class LeaveReportService
{
    /**
     * Report 1: Monthly Leave Transaction Report.
     *
     * Filters by the date the transaction was recorded.
     */
    public function monthlyLeaveTransactions(
        string $startDate,
        string $endDate
    ): Collection {
        $transactions = LeaveBalanceTransaction::query()
            ->with([
                'leaveBalance.employee.user',
                'leaveBalance.leaveType',
                'leaveApplication',
            ])
            ->where('created_at', '>=', Carbon::parse($startDate)->startOfDay())
            ->where('created_at', '<', Carbon::parse($endDate)->startOfDay())
            ->get();

        return $transactions
            ->sortBy(function (LeaveBalanceTransaction $transaction) {
                return strtolower(
                    $transaction->leaveBalance?->employee?->user?->name
                        ?? 'Unknown Employee'
                ) . '|' . $transaction->created_at?->format('Y-m-d H:i:s');
            })
            ->values()
            ->map(function (LeaveBalanceTransaction $transaction) {
                $balance = $transaction->leaveBalance;
                $employee = $balance?->employee;
                $application = $transaction->leaveApplication;

                $type = $transaction->transaction_type;
                $days = abs((float) $transaction->days);

                $additionTypes = [
                    'allocation',
                    'carry_forward',
                    'reversal',
                ];

                $deductionTypes = [
                    'deduction',
                    'carry_forward_out',
                    'expiry',
                ];

                return [
                    $employee?->user?->name ?? 'Unknown Employee',
                    $employee?->joining_date?->format('Y-m-d') ?? '',
                    $balance?->leaveType?->name ?? '',
                    $transaction->created_at?->format('Y-m-d H:i:s') ?? '',
                    $type,
                    in_array($type, $additionTypes, true) ? $days : 0,
                    in_array($type, $deductionTypes, true) ? $days : 0,
                    (float) $transaction->balance_after,
                    $application?->leave_from_date?->format('Y-m-d') ?? '',
                    $application?->leave_to_date?->format('Y-m-d') ?? '',
                    $transaction->description ?? '',
                ];
            });
    }

    /**
     * Report 2: Employee Leave Balance Report.
     *
     * Shows each saved allocation separately.
     */

    public function employeeLeaveBalances(
        ?int $employeeId = null,
        ?int $leaveTypeId = null
    ): Collection {
        return LeaveBalance::query()
            ->with([
                'employee.user',
                'leaveType',
            ])
            ->when(
                $employeeId !== null,
                fn($query) => $query->where('employee_id', $employeeId)
            )
            ->when(
                $leaveTypeId !== null,
                fn($query) => $query->where('leave_type_id', $leaveTypeId)
            )
            ->get()
            ->sortBy(function (LeaveBalance $balance) {
                return strtolower(
                    $balance->employee?->user?->name ?? 'Unknown Employee'
                ) . '|'
                    . strtolower($balance->leaveType?->name ?? '')
                    . '|'
                    . ($balance->period_start?->format('Y-m-d') ?? '');
            })
            ->values()
            ->map(function (LeaveBalance $balance) {
                return [
                    $balance->employee?->user?->name ?? 'Unknown Employee',
                    $balance->employee?->joining_date?->format('Y-m-d') ?? '',
                    $balance->leaveType?->name ?? '',
                    $balance->allocation_type,
                    (float) $balance->allocated_days,
                    round(
                        (float) $balance->allocated_days
                            - (float) $balance->remaining_days,
                        2
                    ),
                    (float) $balance->remaining_days,
                    $balance->period_start?->format('Y-m-d') ?? '',
                    $balance->period_end?->format('Y-m-d') ?? '',
                    $balance->expires_at?->format('Y-m-d') ?? '',
                ];
            });
    }


    /**
     * Report 3: Monthly Leave Balance Summary.
     *
     * Groups movements by employee and leave type.
     * The reporting month is based on transaction creation time.
     */
    public function monthlyLeaveBalanceSummary(
        string $startDate,
        string $endDate
    ): Collection {
        $transactions = LeaveBalanceTransaction::query()
            ->with([
                'leaveBalance.employee.user',
                'leaveBalance.leaveType',
            ])
            ->where('created_at', '>=', Carbon::parse($startDate)->startOfDay())
            ->where('created_at', '<', Carbon::parse($endDate)->startOfDay())
            ->get();

        $groups = $transactions->groupBy(function (
            LeaveBalanceTransaction $transaction
        ) {
            $balance = $transaction->leaveBalance;

            return $balance->employee_id
                . '|'
                . $balance->leave_type_id;
        });

        return $groups->map(function (Collection $group) {
            /** @var LeaveBalanceTransaction $first */
            $first = $group->first();

            $balance = $first->leaveBalance;
            $employee = $balance->employee;
            $leaveType = $balance->leaveType;

            $allocated = 0.0;
            $carryForwardReceived = 0.0;
            $carryForwardTransferred = 0.0;
            $deducted = 0.0;
            $reversed = 0.0;
            $expired = 0.0;

            foreach ($group as $transaction) {
                $days = abs((float) $transaction->days);

                switch ($transaction->transaction_type) {
                    case 'allocation':
                        $allocated += $days;
                        break;

                    case 'carry_forward':
                        $carryForwardReceived += $days;
                        break;

                    case 'carry_forward_out':
                        $carryForwardTransferred += $days;
                        break;

                    case 'deduction':
                        $deducted += $days;
                        break;

                    case 'reversal':
                        $reversed += $days;
                        break;

                    case 'expiry':
                        $expired += $days;
                        break;
                }
            }

            $netChange = $allocated
                + $carryForwardReceived
                + $reversed
                - $deducted
                - $carryForwardTransferred
                - $expired;

            return [
                $employee?->user?->name ?? 'Unknown Employee',
                $employee?->joining_date?->format('Y-m-d') ?? '',
                $leaveType?->name ?? '',
                round($allocated, 2),
                round($carryForwardReceived, 2),
                round($carryForwardTransferred, 2),
                round($deducted, 2),
                round($reversed, 2),
                round($expired, 2),
                round($netChange, 2),
            ];
        })
            ->sortBy(fn(array $row) => strtolower($row[0]) . '|' . strtolower($row[2]))
            ->values();
    }

    /**
     * Report 4: Monthly Employee Leave Register.
     *
     * Includes approved applications that overlap the selected month.
     */
    public function monthlyEmployeeLeaveRegister(
        string $startDate,
        string $endDate
    ): Collection {
        $applications = LeaveApplication::query()
            ->with([
                'employee.user',
                'leaveType',
                'duration',
            ])
            ->where('status', 'approved')
            ->where('leave_from_date', '<', $endDate)
            ->where('leave_to_date', '>=', $startDate)
            ->get();

        return $applications
            ->sortBy(function (LeaveApplication $application) {
                return strtolower(
                    $application->employee?->user?->name
                        ?? 'Unknown Employee'
                ) . '|'
                    . ($application->leave_from_date?->format('Y-m-d') ?? '');
            })
            ->values()
            ->map(function (LeaveApplication $application) {
                return [
                    $application->employee?->user?->name
                        ?? 'Unknown Employee',
                    $application->employee?->joining_date?->format('Y-m-d')
                        ?? '',
                    $application->leaveType?->name ?? '',
                    $application->leave_from_date?->format('Y-m-d') ?? '',
                    $application->leave_to_date?->format('Y-m-d') ?? '',
                    $application->duration?->name ?? '',
                    (float) $application->leavedeductcount,
                    $application->leavereason,
                ];
            });
    }
}
