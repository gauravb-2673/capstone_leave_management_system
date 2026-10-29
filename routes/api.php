<?php

use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public authentication route.
    Route::post('/login', [
        AuthController::class,
        'login',
    ]);

    // Authenticated routes.
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [
            AuthController::class,
            'logout',
        ]);

        Route::get('/me', [
            AuthController::class,
            'me',
        ]);
    });
});
