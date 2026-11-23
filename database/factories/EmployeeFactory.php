<?php

namespace Database\Factories;

use App\Models\Area;
use App\Models\Employee;
use App\Models\Region;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Employee> */
class EmployeeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'employee_code' => strtoupper(fake()->unique()->bothify('EMP-####')),
            'phone' => fake()->optional()->phoneNumber(),
            'region_id' => Region::factory(),
            'area_id' => function (array $attributes) {
                return Area::factory()->create(['region_id' => $attributes['region_id']])->id;
            },
            'designation' => 'Area Manager',
            'status' => 'active',
        ];
    }
}
