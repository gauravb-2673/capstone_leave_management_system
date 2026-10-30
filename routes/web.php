<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\LeaveApproverMappingController;
use App\Http\Controllers\LeaveApplicationController;
use App\Http\Controllers\LeaveApprovalController;
use App\Services\Leave\LeaveCancellationService;
use App\Http\Controllers\Reports\LeaveReportController;
use App\Http\Controllers\Admin\LeaveAllocationController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    // Profile routes
    Route::get('/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');

    Route::patch('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');

    Route::delete('/profile', [ProfileController::class, 'destroy'])
        ->name('profile.destroy');





    // Leave Application routes

    // Display the logged-in employee's application history.
    Route::get('/leave-applications', [
        LeaveApplicationController::class,
        'index',
    ])->name('leave-applications.index');

    // Display the leave application form.
    Route::get('/leave-applications/create', [
        LeaveApplicationController::class,
        'create',
    ])->name('leave-applications.create');

    // Submit a new leave application.
    Route::post('/leave-applications', [
        LeaveApplicationController::class,
        'store',
    ])->name('leave-applications.store');

    Route::post('/leave-applications/{leaveApplication}/cancel', [
        LeaveApplicationController::class,
        'cancel',
    ])->name('leave-applications.cancel');






    // Leave Approval routes
    Route::get('/leave-approvals', [
        LeaveApprovalController::class,
        'index',
    ])->name('leave-approvals.index');

    Route::post('/leave-approvals/{approval}/approve', [
        LeaveApprovalController::class,
        'approve',
    ])->name('leave-approvals.approve');

    Route::post('/leave-approvals/{approval}/reject', [
        LeaveApprovalController::class,
        'reject',
    ])->name('leave-approvals.reject');
});


Route::middleware(['auth', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/employees', [EmployeeController::class, 'index'])
            ->name('employees.index');

        Route::get('/employees/create', [EmployeeController::class, 'create'])
            ->name('employees.create');

        Route::post('/employees', [EmployeeController::class, 'store'])
            ->name('employees.store');

        // Leave Approver Mapping Master
        Route::get('/leave-approver-mappings', [
            LeaveApproverMappingController::class,
            'index',
        ])->name('leave-approver-mappings.index');

        Route::get('/leave-approver-mappings/create', [
            LeaveApproverMappingController::class,
            'create',
        ])->name('leave-approver-mappings.create');

        Route::post('/leave-approver-mappings', [
            LeaveApproverMappingController::class,
            'store',
        ])->name('leave-approver-mappings.store');

        Route::get('/leave-approver-mappings/{leaveApproverMapping}/edit', [
            LeaveApproverMappingController::class,
            'edit',
        ])->name('leave-approver-mappings.edit');

        Route::put('/leave-approver-mappings/{leaveApproverMapping}', [
            LeaveApproverMappingController::class,
            'update',
        ])->name('leave-approver-mappings.update');

        Route::patch('/leave-approver-mappings/{leaveApproverMapping}/toggle-status', [
            LeaveApproverMappingController::class,
            'toggleStatus',
        ])->name('leave-approver-mappings.toggle-status');
        Route::resource('holidays', \App\Http\Controllers\Admin\HolidayController::class)
            ->except(['show']);


        // Admin Leave Application History
        Route::get('/leave-applications', [
            \App\Http\Controllers\Admin\LeaveApplicationHistoryController::class,
            'index',
        ])->name('leave-applications.index');
        Route::post('/leave-applications/{leaveApplication}/cancel', [
            \App\Http\Controllers\Admin\LeaveApplicationHistoryController::class,
            'cancel',
        ])->name('leave-applications.cancel');

        // Leave Reports

        Route::get('/reports/leave/monthly-transactions', [
            LeaveReportController::class,
            'monthlyTransactions',
        ])->name('reports.leave.monthly-transactions');

        Route::get('/reports/leave/employee-balances', [
            LeaveReportController::class,
            'employeeBalances',
        ])->name('reports.leave.employee-balances');

        Route::get('/reports/leave/monthly-balance-summary', [
            LeaveReportController::class,
            'monthlyBalanceSummary',
        ])->name('reports.leave.monthly-balance-summary');

        Route::get('/reports/leave/monthly-register', [
            LeaveReportController::class,
            'monthlyLeaveRegister',
        ])->name('reports.leave.monthly-register');





        Route::patch(
            '/leave-approver-mappings/{leaveApproverMapping}/toggle-status',
            [LeaveApproverMappingController::class, 'toggleStatus']
        )->name('leave-approver-mappings.toggle-status');

        Route::delete(
            '/leave-approver-mappings/{leaveApproverMapping}',
            [LeaveApproverMappingController::class, 'destroy']
        )->name('leave-approver-mappings.destroy');
    });

// Special Leave Allocation, Administration features, and Reports
// accessible to both Admin and HR.
Route::middleware(['auth', 'admin-or-hr'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        // Special Leave Allocations
        Route::get('/leave-allocations', [
            LeaveAllocationController::class,
            'index',
        ])->name('leave-allocations.index');

        Route::get('/leave-allocations/create', [
            LeaveAllocationController::class,
            'create',
        ])->name('leave-allocations.create');

        Route::post('/leave-allocations', [
            LeaveAllocationController::class,
            'store',
        ])->name('leave-allocations.store');

        Route::delete('/leave-allocations/{leaveBalance}', [
            LeaveAllocationController::class,
            'destroy',
        ])->name('leave-allocations.destroy');

        // Leave Reports
        Route::get('/reports/leave/monthly-transactions', [
            LeaveReportController::class,
            'monthlyTransactions',
        ])->name('reports.leave.monthly-transactions');

        Route::get('/reports/leave/employee-balances', [
            LeaveReportController::class,
            'employeeBalances',
        ])->name('reports.leave.employee-balances');

        Route::get('/reports/leave/monthly-balance-summary', [
            LeaveReportController::class,
            'monthlyBalanceSummary',
        ])->name('reports.leave.monthly-balance-summary');

        Route::get('/reports/leave/monthly-register', [
            LeaveReportController::class,
            'monthlyLeaveRegister',
        ])->name('reports.leave.monthly-register');
    });








require __DIR__ . '/auth.php';
