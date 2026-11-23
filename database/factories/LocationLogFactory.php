<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\Employee;
use App\Models\LocationLog;
use App\Models\TrackingSession;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<LocationLog> */
class LocationLogFactory extends Factory
{
    public function definition(): array
    {
        $recordedAt = now();

        return [
            'event_uuid' => (string) Str::uuid(),
            'employee_id' => Employee::factory(),
            'device_id' => Device::factory(),
            'session_id' => TrackingSession::factory(),
            'latitude' => fake()->latitude(23, 27),
            'longitude' => fake()->longitude(67, 71),
            'accuracy' => fake()->randomFloat(2, 3, 50),
            'speed' => fake()->randomFloat(2, 0, 30),
            'bearing' => fake()->randomFloat(2, 0, 359),
            'altitude' => fake()->randomFloat(2, 0, 500),
            'battery_level' => fake()->numberBetween(10, 100),
            'recorded_at' => $recordedAt,
            'received_at' => $recordedAt->copy()->addSeconds(2),
        ];
    }
}
