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

    public function test_forgotten_extra_duty_auto_stops_at_midnight_as_uncounted(): void
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

        $this->withToken($token)->postJson('/api/v1/tracking/start', [
            'device_uuid' => $device->device_uuid,
            'mode' => 'extra_duty',
            'client_midnight_at' => Carbon::parse('2026-11-25 00:00:00', 'Asia/Dhaka')->toISOString(),
        ])->assertCreated();

        Carbon::setTestNow('2026-11-25 00:05:00');
        $status = $this->withToken($token)->getJson('/api/v1/tracking/status');

        $status->assertOk()
            ->assertJsonPath('data.active_session.status', 'expired')
            ->assertJsonPath('data.active_session.stop_reason', 'midnight_auto')
            ->assertJsonPath('data.active_session.counted', false);

        $this->assertDatabaseHas('tracking_sessions', [
            'employee_id' => $employee->id,
            'session_type' => 'extra_duty',
            'status' => 'expired',
            'stop_reason' => 'midnight_auto',
            'counted' => false,
        ]);

        Carbon::setTestNow();
    }

    public function test_status_uses_client_midnight_at_for_phone_local_day_metadata(): void
    {
        Carbon::setTestNow('2026-11-24 22:00:00');
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

        TrackingSession::factory()->create([
            'employee_id' => $employee->id,
            'device_id' => $device->id,
            'status' => TrackingSessionStatus::STOPPED,
            'session_type' => TrackingSessionType::EXTRA_DUTY,
            'started_at' => Carbon::parse('2026-11-24 21:30:00', 'Asia/Dhaka'),
            'ended_at' => Carbon::parse('2026-11-24 22:00:00', 'Asia/Dhaka'),
            'expires_at' => Carbon::parse('2026-11-25 00:00:00', 'Asia/Dhaka'),
            'stop_reason' => StopReason::EXTENDED_MANUAL,
            'counted' => true,
        ]);

        $sameDayWindow = Carbon::parse('2026-11-25 00:00:00', 'Asia/Dhaka')->toISOString();
        $nextDayWindow = Carbon::parse('2026-11-26 00:00:00', 'Asia/Dhaka')->toISOString();

        $this->withToken($token)->getJson('/api/v1/tracking/status?client_midnight_at='.urlencode($sameDayWindow))
            ->assertOk()
            ->assertJsonPath('data.has_used_extra_today', true)
            ->assertJsonPath('data.can_extend', false);

        $this->withToken($token)->getJson('/api/v1/tracking/status?client_midnight_at='.urlencode($nextDayWindow))
            ->assertOk()
            ->assertJsonPath('data.has_used_extra_today', false);

        Carbon::setTestNow();
    }
}
