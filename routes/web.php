<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\LeaveApproverMappingController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
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
    });


require __DIR__ . '/auth.php';
