<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\LeaveApplicationController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\LeaveApprovalController;
use App\Http\Controllers\Api\V1\LeaveReportController;

Route::prefix('v1')->group(function () {

    // Public authentication route.
    Route::post('/login', [
        AuthController::class,
        'login',
    ]);

    // All routes below require Sanctum authentication.
    Route::middleware('auth:sanctum')->group(function () {

        Route::post('/logout', [
            AuthController::class,
            'logout',
        ]);

        Route::get('/me', [
            AuthController::class,
            'me',
        ]);

        // Leave application API routes.
        Route::get('/leave-applications', [
            LeaveApplicationController::class,
            'index',
        ]);

        Route::get('/leave-applications/{id}', [
            LeaveApplicationController::class,
            'show',
        ]);

        Route::post('/leave-applications/{id}/cancel', [
            LeaveApplicationController::class,
            'cancel',
        ]);

        Route::post('/leave-applications', [LeaveApplicationController::class, 'store']);

        Route::post('/leave-approvals/{id}/approve', [LeaveApprovalController::class, 'approve']);
        Route::post('/leave-approvals/{id}/reject', [LeaveApprovalController::class, 'reject']);

        Route::middleware('admin-or-hr')->prefix('reports/leave')->group(function () {
            Route::get('/monthly-transactions', [
                LeaveReportController::class,
                'monthlyTransactions',
            ]);

            Route::get('/employee-balances', [
                LeaveReportController::class,
                'employeeBalances',
            ]);

            Route::get('/monthly-balance-summary', [
                LeaveReportController::class,
                'monthlyBalanceSummary',
            ]);

            Route::get('/monthly-register', [
                LeaveReportController::class,
                'monthlyRegister',
            ]);
        });
    });
});
