<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class HolidaySeeder extends Seeder
{
    public function run(): void
    {
        $holidays = [
            [
                'holiday_date' => '2026-01-26',
                'name' => 'Republic Day',
                'description' => 'Sample national holiday',
            ],
            [
                'holiday_date' => '2026-08-15',
                'name' => 'Independence Day',
                'description' => 'Sample national holiday',
            ],
            [
                'holiday_date' => '2026-12-25',
                'name' => 'Christmas',
                'description' => 'Sample holiday',
            ],
        ];

        foreach ($holidays as $holiday) {
            DB::table('holidays')->updateOrInsert(
                ['holiday_date' => $holiday['holiday_date']],
                [
                    'name' => $holiday['name'],
                    'description' => $holiday['description'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
