<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // DB::listen(function ($query) {
        //     Log::debug('Database Query', [
        //         'sql' => $query->sql,
        //         'bindings' => $query->bindings,
        //         'time_ms' => $query->time,
        //     ]);
        // });
    }
}
