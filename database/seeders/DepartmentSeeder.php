<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            'Human Resources',
            'Engineering',
            'Finance',
            'Operations',
        ];

        foreach ($departments as $departmentName) {
            Department::firstOrCreate([
                'department_name' => $departmentName,
            ]);
        }
    }
}
