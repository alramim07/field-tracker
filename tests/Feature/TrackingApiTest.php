<?php

namespace Tests\Feature;

use App\Enums\TrackingSessionStatus;
use App\Models\Device;
use App\Models\Employee;
use App\Models\TrackingPolicy;
use App\Models\TrackingSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TrackingApiTest extends TestCase
{
    use RefreshDatabase;

    private function makeEmployeeWithDevice(array $employeeAttributes = []): array
    {
        $employee = Employee::factory()->create(array_merge([
            'duty_start_time' => '09:00:00',
            'duty_end_time' => '18:00:00',
        ], $employeeAttributes));
        $device = Device::factory()->create(['employee_id' => $employee->id]);
        $token = $employee->user->createToken('test')->plainTextToken;
        TrackingPolicy::factory()->create(['duration_minutes' => 540]);

        return [$employee, $device, $token];
    }

    public function test_employee_can_start_tracking_session_with_fixed_expiry(): void
    {
        Carbon::setTestNow('2026-11-23 09:00:00');
        [, $device, $token] = $this->makeEmployeeWithDevice();

        $response = $this->withToken($token)->postJson('/api/v1/tracking/start', [
            'device_uuid' => $device->device_uuid,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.session_type', 'normal_duty')
            ->assertJsonPath('data.expires_at', Carbon::parse('2026-11-23 18:00:00')->toISOString());

        $this->assertDatabaseHas('tracking_sessions', [
            'employee_id' => $device->employee_id,
            'device_id' => $device->id,
            'status' => 'active',
        ]);
        Carbon::setTestNow();
    }

    public function test_employee_cannot_start_duplicate_active_session(): void
    {
        [$employee, $device, $token] = $this->makeEmployeeWithDevice();
        TrackingSession::factory()->create([
            'employee_id' => $employee->id,
            'device_id' => $device->id,
            'status' => TrackingSessionStatus::ACTIVE,
            'started_at' => now(),
            'expires_at' => now()->addHours(9),
        ]);

        $this->withToken($token)->postJson('/api/v1/tracking/start', [
            'device_uuid' => $device->device_uuid,
        ])->assertUnprocessable()->assertJsonValidationErrors(['session']);
    }

    public function test_employee_cannot_manually_stop_normal_duty_but_can_stop_extra_duty(): void
    {
        Carbon::setTestNow('2026-11-23 09:00:00');
        [$employee, $device, $token] = $this->makeEmployeeWithDevice();
        $this->withToken($token)->postJson('/api/v1/tracking/start', ['device_uuid' => $device->device_uuid])->assertCreated();

        $this->withToken($token)->postJson('/api/v1/tracking/stop')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['session']);

        Carbon::setTestNow('2026-11-23 18:30:00');
        $this->withToken($token)->getJson('/api/v1/tracking/status')
            ->assertOk()
            ->assertJsonPath('data.active_session.status', 'expired')
            ->assertJsonPath('data.active_session.stop_reason', 'expired');

        $extra = $this->withToken($token)->postJson('/api/v1/tracking/start', [
            'device_uuid' => $device->device_uuid,
            'mode' => 'extra_duty',
            'client_midnight_at' => Carbon::parse('2026-11-24 00:00:00', 'Asia/Dhaka')->toISOString(),
        ]);
        $extra->assertCreated()->assertJsonPath('data.session_type', 'extra_duty');

        $stop = $this->withToken($token)->postJson('/api/v1/tracking/stop');
        $stop->assertOk()
            ->assertJsonPath('data.status', 'stopped')
            ->assertJsonPath('data.stop_reason', 'extended_manual');

        $status = $this->withToken($token)->getJson('/api/v1/tracking/status');
        $status->assertOk()->assertJsonPath('data.active_session.status', 'stopped');
        Carbon::setTestNow();
    }
}
