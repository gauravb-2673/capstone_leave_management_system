<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LeaveTypeSeeder extends Seeder
{
    public function run(): void
    {
        $leaveTypes = [
            ['code' => 'CL', 'name' => 'Casual Leave'],
            ['code' => 'SL', 'name' => 'Sick Leave'],
            ['code' => 'PL', 'name' => 'Privilege Leave'],
            ['code' => 'WFH', 'name' => 'Work From Home'],
            ['code' => 'LWP', 'name' => 'Leave Without Pay'],
            ['code' => 'MT', 'name' => 'Maternity Leave'],
            ['code' => 'PT', 'name' => 'Paternity Leave'],
        ];

        foreach ($leaveTypes as $leaveType) {
            DB::table('leavetypes')->updateOrInsert(
                ['code' => $leaveType['code']],
                [
                    'name' => $leaveType['name'],
                    'is_active' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
