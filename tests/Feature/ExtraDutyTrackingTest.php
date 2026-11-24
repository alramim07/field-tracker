<?php

namespace Tests\Feature;

use App\Enums\StopReason;
use App\Enums\TrackingSessionStatus;
use App\Enums\TrackingSessionType;
use App\Models\Device;
use App\Models\Employee;
use App\Models\TrackingPolicy;
use App\Models\TrackingSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ExtraDutyTrackingTest extends TestCase
{
    use RefreshDatabase;

    private function makeEmployeeWithDevice(array $employeeAttributes = []): array
    {
        $employee = Employee::factory()->create($employeeAttributes);
        $device = Device::factory()->create(['employee_id' => $employee->id]);
        $token = $employee->user->createToken('test')->plainTextToken;
        TrackingPolicy::factory()->create(['duration_minutes' => 540]);

        return [$employee, $device, $token];
    }

    public function test_normal_session_expires_at_duty_end(): void
    {
        Carbon::setTestNow('2026-11-24 09:00:00');
        [$employee, $device, $token] = $this->makeEmployeeWithDevice([
            'duty_start_time' => '09:00:00',
            'duty_end_time' => '21:00:00',
        ]);

        $this->withToken($token)->postJson('/api/v1/tracking/start', [
            'device_uuid' => $device->device_uuid,
            'mode' => 'normal_duty',
        ])->assertCreated()
            ->assertJsonPath('data.session_type', 'normal_duty')
            ->assertJsonPath('data.expires_at', Carbon::parse('2026-11-24 21:00:00', 'Asia/Dhaka')->utc()->toISOString());

        Carbon::setTestNow();
    }

    public function test_extra_duty_requires_normal_session_ended_and_only_once_per_day(): void
    {
        Carbon::setTestNow('2026-11-24 21:30:00');
        [$employee, $device, $token] = $this->makeEmployeeWithDevice([
            'duty_start_time' => '09:00:00',
            'duty_end_time' => '21:00:00',
        ]);

        TrackingSession::factory()->create([
            'employee_id' => $employee->id,
            'device_id' => $device->id,
            'status' => TrackingSessionStatus::STOPPED,
            'session_type' => TrackingSessionType::NORMAL_DUTY,
            'started_at' => Carbon::parse('2026-11-24 09:00:00', 'Asia/Dhaka'),
            'ended_at' => Carbon::parse('2026-11-24 21:00:00', 'Asia/Dhaka'),
            'expires_at' => Carbon::parse('2026-11-24 21:00:00', 'Asia/Dhaka'),
            'stop_reason' => StopReason::EXPIRED,
            'counted' => true,
        ]);

        $payload = [
            'device_uuid' => $device->device_uuid,
            'mode' => 'extra_duty',
            'client_midnight_at' => Carbon::parse('2026-11-25 00:00:00', 'Asia/Dhaka')->toISOString(),
        ];

        $this->withToken($token)->postJson('/api/v1/tracking/start', $payload)
            ->assertCreated()
            ->assertJsonPath('data.session_type', 'extra_duty');

        $this->withToken($token)->postJson('/api/v1/tracking/start', $payload)
            ->assertUnprocessable();

        Carbon::setTestNow();
    }
}
