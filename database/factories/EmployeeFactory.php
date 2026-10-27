<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'department_id' => null,
            'employee_code' => fake()->unique()->bothify('EMP-####-????'),
            'joining_date' => fake()->dateTimeBetween('-5 years', 'today')->format('Y-m-d'),
            'is_active' => true,
        ];
    }
}
