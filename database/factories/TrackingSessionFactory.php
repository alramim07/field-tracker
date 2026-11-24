<?php

namespace Database\Factories;

use App\Enums\TrackingSessionStatus;
use App\Enums\TrackingSessionType;
use App\Models\Device;
use App\Models\Employee;
use App\Models\TrackingPolicy;
use App\Models\TrackingSession;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<TrackingSession> */
class TrackingSessionFactory extends Factory
{
    public function definition(): array
    {
        $startedAt = now();
        $policy = TrackingPolicy::factory()->create();

        return [
            'session_uuid' => (string) Str::uuid(),
            'employee_id' => Employee::factory(),
            'device_id' => function (array $attributes) {
                return Device::factory()->create(['employee_id' => $attributes['employee_id']])->id;
            },
            'tracking_policy_id' => $policy->id,
            'policy_snapshot' => $policy->only([
                'name', 'start_time', 'end_time', 'duration_minutes',
                'moving_interval_seconds', 'stationary_interval_seconds',
            ]),
            'started_at' => $startedAt,
            'expires_at' => $startedAt->copy()->addMinutes($policy->duration_minutes),
            'ended_at' => null,
            'status' => TrackingSessionStatus::ACTIVE,
            'stop_reason' => null,
            'session_type' => TrackingSessionType::NORMAL_DUTY,
            'counted' => true,
        ];
    }
}
