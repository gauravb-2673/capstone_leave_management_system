<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\EmployeeLeaveBalancesExport;
use App\Exports\MonthlyEmployeeLeaveRegisterExport;
use App\Exports\MonthlyLeaveBalanceSummaryExport;
use App\Exports\MonthlyLeaveTransactionsExport;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Services\Reports\LeaveReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Excel as ExcelWriter;

class LeaveReportController extends Controller
{
    /**
     * Report 1: Monthly Leave Transactions.
     */
    public function monthlyTransactions(
        Request $request,
        LeaveReportService $reportService
    ) {
        $validated = $request->validate([
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:start_date',
            ],
            'format' => ['sometimes', 'string', Rule::in(['xlsx', 'csv'])],
        ]);

        $startDate = Carbon::parse($validated['start_date'])->startOfDay();
        $exclusiveEndDate = Carbon::parse($validated['end_date'])
            ->addDay()
            ->startOfDay();

        $rows = $reportService->monthlyLeaveTransactions(
            $startDate->toDateString(),
            $exclusiveEndDate->toDateString()
        );

        $export = new MonthlyLeaveTransactionsExport($rows);

        $format = $validated['format'] ?? 'xlsx';

        $filename = sprintf(
            'leave-transactions-%s-to-%s.%s',
            $validated['start_date'],
            $validated['end_date'],
            $format
        );

        return Excel::download(
            $export,
            $filename,
            $this->writerType($format)
        );
    }

    /**
     * Report 2: Employee Leave Balances.
     */
    public function employeeBalances(
        Request $request,
        LeaveReportService $reportService
    ) {
        $validated = $request->validate([
            'employee_id' => [
                'nullable',
                'integer',
                Rule::exists((new Employee)->getTable(), 'id'),
            ],
            'leave_type_id' => [
                'nullable',
                'integer',
                Rule::exists((new LeaveType)->getTable(), 'id'),
            ],
            'format' => ['sometimes', 'string', Rule::in(['xlsx', 'csv'])],
        ]);

        $rows = $reportService->employeeLeaveBalances(
            isset($validated['employee_id'])
                ? (int) $validated['employee_id']
                : null,
            isset($validated['leave_type_id'])
                ? (int) $validated['leave_type_id']
                : null
        );

        $export = new EmployeeLeaveBalancesExport($rows);

        $format = $validated['format'] ?? 'xlsx';

        return Excel::download(
            $export,
            "employee-leave-balances.{$format}",
            $this->writerType($format)
        );
    }

    /**
     * Report 3: Monthly Leave Balance Summary.
     */
    public function monthlyBalanceSummary(
        Request $request,
        LeaveReportService $reportService
    ) {
        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'format' => ['sometimes', 'string', Rule::in(['xlsx', 'csv'])],
        ]);

        $startDate = Carbon::createFromFormat(
            'Y-m',
            $validated['month']
        )->startOfMonth();

        $exclusiveEndDate = $startDate->copy()->addMonth();

        $rows = $reportService->monthlyLeaveBalanceSummary(
            $startDate->toDateString(),
            $exclusiveEndDate->toDateString()
        );

        $export = new MonthlyLeaveBalanceSummaryExport($rows);

        $format = $validated['format'] ?? 'xlsx';

        return Excel::download(
            $export,
            "leave-balance-summary-{$validated['month']}.{$format}",
            $this->writerType($format)
        );
    }

    /**
     * Report 4: Monthly Employee Leave Register.
     */
    public function monthlyRegister(
        Request $request,
        LeaveReportService $reportService
    ) {
        $validated = $request->validate([
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:start_date',
            ],
            'format' => ['sometimes', 'string', Rule::in(['xlsx', 'csv'])],
        ]);

        $startDate = Carbon::parse($validated['start_date'])->startOfDay();
        $exclusiveEndDate = Carbon::parse($validated['end_date'])
            ->addDay()
            ->startOfDay();

        $rows = $reportService->monthlyEmployeeLeaveRegister(
            $startDate->toDateString(),
            $exclusiveEndDate->toDateString()
        );

        $export = new MonthlyEmployeeLeaveRegisterExport($rows);

        $format = $validated['format'] ?? 'xlsx';

        $filename = sprintf(
            'employee-leave-register-%s-to-%s.%s',
            $validated['start_date'],
            $validated['end_date'],
            $format
        );

        return Excel::download(
            $export,
            $filename,
            $this->writerType($format)
        );
    }

    /**
     * Convert the requested format into a Laravel Excel writer type.
     */
    private function writerType(string $format): string
    {
        return $format === 'csv'
            ? ExcelWriter::CSV
            : ExcelWriter::XLSX;
    }
}
