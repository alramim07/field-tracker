<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\LatestLocation;
use App\Models\TrackingSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LatestLocation> */
class LatestLocationFactory extends Factory
{
    public function definition(): array
    {
        $recordedAt = now();

        return [
            'employee_id' => Employee::factory(),
            'session_id' => TrackingSession::factory(),
            'latitude' => fake()->latitude(20, 27),
            'longitude' => fake()->longitude(88, 92),
            'accuracy' => fake()->randomFloat(2, 3, 50),
            'speed' => fake()->randomFloat(2, 0, 30),
            'bearing' => fake()->randomFloat(2, 0, 359),
            'battery_level' => fake()->numberBetween(10, 100),
            'recorded_at' => $recordedAt,
            'received_at' => $recordedAt->copy()->addSeconds(2),
        ];
    }
}
