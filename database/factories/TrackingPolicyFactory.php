<?php

namespace Database\Factories;

use App\Enums\TrackingPolicyStatus;
use App\Models\TrackingPolicy;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TrackingPolicy> */
class TrackingPolicyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Default Duty Policy',
            'start_time' => '09:00:00',
            'end_time' => '18:00:00',
            'duration_minutes' => 540,
            'moving_interval_seconds' => 5,
            'stationary_interval_seconds' => 30,
            'status' => TrackingPolicyStatus::ACTIVE,
            'created_by' => User::factory()->superAdmin(),
        ];
    }
}
