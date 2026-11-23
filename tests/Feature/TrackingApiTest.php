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

    private function makeEmployeeWithDevice(): array
    {
        $employee = Employee::factory()->create();
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

    public function test_employee_can_stop_and_get_status(): void
    {
        [$employee, $device, $token] = $this->makeEmployeeWithDevice();
        $this->withToken($token)->postJson('/api/v1/tracking/start', ['device_uuid' => $device->device_uuid])->assertCreated();

        $stop = $this->withToken($token)->postJson('/api/v1/tracking/stop');
        $stop->assertOk()->assertJsonPath('data.status', 'stopped');

        $status = $this->withToken($token)->getJson('/api/v1/tracking/status');
        $status->assertOk()->assertJsonPath('data.active_session.status', 'stopped');
    }
}
