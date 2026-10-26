<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DurationSeeder extends Seeder
{
    public function run(): void
    {
        $durations = [
            [
                'name' => 'Full Day',
                'days' => 1.00,
            ],
            [
                'name' => 'Half Day',
                'days' => 0.50,
            ],
            [
                'name' => 'Multiple Days',
                'days' => 1.00,
            ],
        ];

        foreach ($durations as $duration) {
            DB::table('durations')->updateOrInsert(
                ['name' => $duration['name']],
                [
                    'days' => $duration['days'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
