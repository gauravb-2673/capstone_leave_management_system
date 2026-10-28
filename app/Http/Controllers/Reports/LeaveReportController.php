<?php

namespace App\Http\Controllers\Reports;

use App\Exports\EmployeeLeaveBalancesExport;
use App\Exports\MonthlyEmployeeLeaveRegisterExport;
use App\Exports\MonthlyLeaveBalanceSummaryExport;
use App\Exports\MonthlyLeaveTransactionsExport;
use App\Http\Controllers\Controller;
use App\Services\Reports\LeaveReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LeaveReportController extends Controller
{
    public function __construct(
        private LeaveReportService $reportService
    ) {}

    /**
     * Download the monthly leave transaction report.
     */
    public function monthlyTransactions(
        Request $request
    ): BinaryFileResponse {
        $month = $this->validatedMonth($request);
        [$startDate, $endDate] = $this->monthRange($month);

        $rows = $this->reportService->monthlyLeaveTransactions(
            $startDate,
            $endDate
        );

        return Excel::download(
            new MonthlyLeaveTransactionsExport($rows),
            "monthly-leave-transactions-{$month}.xlsx"
        );
    }

    /**
     * Download the employee leave balance report.
     *
     * This report includes all saved allocations, not only
     * allocations created during the selected month.
     */
    public function employeeBalances(
        Request $request
    ): BinaryFileResponse {
        $this->validatedMonth($request);

        $rows = $this->reportService->employeeLeaveBalances();

        return Excel::download(
            new EmployeeLeaveBalancesExport($rows),
            'employee-leave-balances.xlsx'
        );
    }

    /**
     * Download the monthly leave balance summary.
     */
    public function monthlyBalanceSummary(
        Request $request
    ): BinaryFileResponse {
        $month = $this->validatedMonth($request);
        [$startDate, $endDate] = $this->monthRange($month);

        $rows = $this->reportService->monthlyLeaveBalanceSummary(
            $startDate,
            $endDate
        );

        return Excel::download(
            new MonthlyLeaveBalanceSummaryExport($rows),
            "monthly-leave-balance-summary-{$month}.xlsx"
        );
    }

    /**
     * Download the monthly employee leave register.
     */
    public function monthlyLeaveRegister(
        Request $request
    ): BinaryFileResponse {
        $month = $this->validatedMonth($request);
        [$startDate, $endDate] = $this->monthRange($month);

        $rows = $this->reportService->monthlyEmployeeLeaveRegister(
            $startDate,
            $endDate
        );

        return Excel::download(
            new MonthlyEmployeeLeaveRegisterExport($rows),
            "monthly-employee-leave-register-{$month}.xlsx"
        );
    }

    /**
     * Validate the optional month parameter.
     *
     * Expected format: YYYY-MM, for example 2026-10.
     */
    private function validatedMonth(Request $request): string
    {
        $validated = $request->validate([
            'month' => [
                'sometimes',
                'required',
                'date_format:Y-m',
            ],
        ]);

        return $validated['month'] ?? now()->format('Y-m');
    }

    /**
     * Return the inclusive start and exclusive end of a month.
     *
     * For October 2026:
     * start = 2026-10-01
     * end   = 2026-11-01
     */
    private function monthRange(string $month): array
    {
        $start = Carbon::createFromFormat('!Y-m', $month);

        return [
            $start->copy()->startOfMonth()->toDateString(),
            $start->copy()->addMonth()->startOfMonth()->toDateString(),
        ];
    }
}
