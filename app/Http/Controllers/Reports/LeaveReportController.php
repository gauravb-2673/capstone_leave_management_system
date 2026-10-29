<?php

namespace App\Http\Controllers\Reports;

use App\Exports\EmployeeLeaveBalancesExport;
use App\Exports\MonthlyEmployeeLeaveRegisterExport;
use App\Exports\MonthlyLeaveBalanceSummaryExport;
use App\Exports\MonthlyLeaveTransactionsExport;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Services\Reports\LeaveReportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LeaveReportController extends Controller
{
    public function __construct(
        private LeaveReportService $reportService
    ) {}

    /**
     * Monthly Leave Transactions.
     */
    public function monthlyTransactions(Request $request): View|BinaryFileResponse
    {
        if (! $request->boolean('generate')) {
            return $this->filterForm(
                'date_range',
                'Monthly Leave Transactions',
                'View leave balance transactions created during your selected date range.',
                route('admin.reports.leave.monthly-transactions')
            );
        }

        [$startDate, $exclusiveEndDate, $endDate] =
            $this->validatedDateRange($request);

        $rows = $this->reportService->monthlyLeaveTransactions(
            $startDate,
            $exclusiveEndDate
        );

        return Excel::download(
            new MonthlyLeaveTransactionsExport($rows),
            "leave-transactions-{$startDate}-to-{$endDate}.xlsx"
        );
    }

    /**
     * Employee Leave Balances.
     */
    public function employeeBalances(Request $request): View|BinaryFileResponse
    {
        if (! $request->boolean('generate')) {
            return $this->filterForm(
                'balances',
                'Employee Leave Balances',
                'Optionally filter current recorded leave balances by employee and leave type.',
                route('admin.reports.leave.employee-balances')
            );
        }

        $employee = new Employee();
        $leaveType = new LeaveType();

        $validated = $request->validate([
            'employee_id' => [
                'nullable',
                Rule::exists($employee->getTable(), $employee->getKeyName()),
            ],
            'leave_type_id' => [
                'nullable',
                Rule::exists($leaveType->getTable(), $leaveType->getKeyName()),
            ],
        ]);

        $rows = $this->reportService->employeeLeaveBalances(
            isset($validated['employee_id'])
                ? (int) $validated['employee_id']
                : null,
            isset($validated['leave_type_id'])
                ? (int) $validated['leave_type_id']
                : null
        );

        return Excel::download(
            new EmployeeLeaveBalancesExport($rows),
            'employee-leave-balances.xlsx'
        );
    }

    /**
     * Monthly Leave Balance Summary.
     */
    public function monthlyBalanceSummary(Request $request): View|BinaryFileResponse
    {
        if (! $request->boolean('generate')) {
            return $this->filterForm(
                'month',
                'Monthly Leave Balance Summary',
                'Summarize leave allocations, carry-forwards, deductions, reversals, and expirations for one calendar month.',
                route('admin.reports.leave.monthly-balance-summary')
            );
        }

        $validated = $request->validate([
            'month' => [
                'required',
                'date_format:Y-m',
            ],
        ]);

        $month = $validated['month'];

        $start = Carbon::createFromFormat('!Y-m', $month);
        $startDate = $start->copy()->startOfMonth()->toDateString();
        $exclusiveEndDate = $start->copy()->addMonth()->startOfMonth()->toDateString();

        $rows = $this->reportService->monthlyLeaveBalanceSummary(
            $startDate,
            $exclusiveEndDate
        );

        return Excel::download(
            new MonthlyLeaveBalanceSummaryExport($rows),
            "leave-balance-summary-{$month}.xlsx"
        );
    }

    /**
     * Monthly Employee Leave Register.
     */
    public function monthlyLeaveRegister(Request $request): View|BinaryFileResponse
    {
        if (! $request->boolean('generate')) {
            return $this->filterForm(
                'date_range',
                'Employee Leave Register',
                'View approved leave applications that overlap your selected date range.',
                route('admin.reports.leave.monthly-register')
            );
        }

        [$startDate, $exclusiveEndDate, $endDate] =
            $this->validatedDateRange($request);

        $rows = $this->reportService->monthlyEmployeeLeaveRegister(
            $startDate,
            $exclusiveEndDate
        );

        return Excel::download(
            new MonthlyEmployeeLeaveRegisterExport($rows),
            "employee-leave-register-{$startDate}-to-{$endDate}.xlsx"
        );
    }

    /**
     * Validate a date range and return an exclusive end date for queries.
     */
    private function validatedDateRange(Request $request): array
    {
        $validated = $request->validate([
            'start_date' => [
                'required',
                'date_format:Y-m-d',
            ],
            'end_date' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:start_date',
            ],
        ]);

        $startDate = $validated['start_date'];
        $endDate = $validated['end_date'];

        // Include the selected end date by querying up to the following day.
        $exclusiveEndDate = Carbon::parse($endDate)
            ->addDay()
            ->toDateString();

        return [
            $startDate,
            $exclusiveEndDate,
            $endDate,
        ];
    }

    /**
     * Display the reusable report filter form.
     */
    private function filterForm(
        string $filterType,
        string $title,
        string $description,
        string $action
    ): View {
        $employees = Employee::query()
            ->with('user')
            ->get()
            ->sortBy(fn(Employee $employee) => strtolower(
                $employee->user?->name ?? ''
            ))
            ->values();

        $leaveTypes = LeaveType::query()
            ->orderBy('name')
            ->get();

        return view('admin.reports.leave.filters', [
            'filterType' => $filterType,
            'title' => $title,
            'description' => $description,
            'action' => $action,
            'employees' => $employees,
            'leaveTypes' => $leaveTypes,
            'defaultStartDate' => now()->startOfMonth()->toDateString(),
            'defaultEndDate' => now()->toDateString(),
            'defaultMonth' => now()->format('Y-m'),
        ]);
    }
}
