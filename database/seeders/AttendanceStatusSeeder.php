<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AttendanceStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['code' => 'P', 'name' => 'Present'],
            ['code' => 'A', 'name' => 'Absent'],
            ['code' => 'WO', 'name' => 'Weekly Off'],
            ['code' => 'HO', 'name' => 'Holiday'],
        ];

        foreach ($statuses as $status) {
            DB::table('attendancestatuses')->updateOrInsert(
                ['code' => $status['code']],
                [
                    'name' => $status['name'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
