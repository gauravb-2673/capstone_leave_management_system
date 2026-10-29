<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Leave Balance Allocation Schedule
|--------------------------------------------------------------------------
|
| Run the leave allocation command every day at 12:05 AM India time.
|
| The timezone applies to this scheduled task, not to the entire
| Laravel application.
|
| withoutOverlapping() prevents a new scheduled execution from starting
| while the previous execution is still running.
|
*/

Schedule::command('leave:allocate-balances')
    ->dailyAt('00:05')
    ->timezone('Asia/Kolkata')
    ->withoutOverlapping();

Schedule::command('leave:expire-special-allocations')
    ->dailyAt('00:10')
    ->timezone('Asia/Kolkata')
    ->withoutOverlapping();
