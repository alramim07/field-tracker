<?php

namespace Database\Factories;

use App\Enums\DeviceStatus;
use App\Models\Device;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Device> */
class DeviceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'device_uuid' => (string) Str::uuid(),
            'manufacturer' => fake()->randomElement(['Samsung', 'Xiaomi', 'Oppo', 'Vivo', 'Realme']),
            'model' => fake()->bothify('Model-??##'),
            'android_version' => fake()->randomElement(['12', '13', '14', '15']),
            'app_version' => '1.0.0',
            'status' => DeviceStatus::ACTIVE,
            'last_seen_at' => now(),
            'registered_at' => now(),
        ];
    }
}
