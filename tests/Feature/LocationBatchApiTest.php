<?php

namespace Tests\Feature;

use App\Enums\TrackingSessionStatus;
use App\Events\LatestLocationUpdated;
use App\Models\Device;
use App\Models\Employee;
use App\Models\LatestLocation;
use App\Models\LocationLog;
use App\Models\TrackingSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class LocationBatchApiTest extends TestCase
{
    use RefreshDatabase;

    private function activeSession(): array
    {
        $employee = Employee::factory()->create();
        $device = Device::factory()->create(['employee_id' => $employee->id]);
        $session = TrackingSession::factory()->create([
            'employee_id' => $employee->id,
            'device_id' => $device->id,
            'status' => TrackingSessionStatus::ACTIVE,
            'started_at' => now()->subMinutes(10),
            'expires_at' => now()->addMinutes(50),
        ]);
        $token = $employee->user->createToken('test')->plainTextToken;

        return [$employee, $device, $session, $token];
    }

    public function test_valid_batch_is_accepted_and_updates_latest_location(): void
    {
        [, $device, $session, $token] = $this->activeSession();
        $eventUuid = (string) Str::uuid();

        $response = $this->withToken($token)->postJson('/api/v1/locations/batch', [
            'device_uuid' => $device->device_uuid,
            'locations' => [[
                'event_uuid' => $eventUuid,
                'session_uuid' => $session->session_uuid,
                'sequence_number' => 1,
                'latitude' => 24.8607,
                'longitude' => 67.0011,
                'accuracy' => 10.5,
                'speed' => 1.2,
                'bearing' => 90,
                'altitude' => 12,
                'battery_level' => 80,
                'recorded_at' => now()->subMinute()->toISOString(),
            ]],
        ]);

        $response->assertOk()->assertJsonPath('data.accepted', 1);
        $this->assertDatabaseHas('location_logs', ['event_uuid' => $eventUuid, 'session_id' => $session->id]);
        $this->assertDatabaseHas('latest_locations', ['employee_id' => $session->employee_id]);
    }

    public function test_duplicate_event_uuid_is_not_inserted_twice(): void
    {
        [, $device, $session, $token] = $this->activeSession();
        $eventUuid = (string) Str::uuid();
        $payload = [
            'device_uuid' => $device->device_uuid,
            'locations' => [[
                'event_uuid' => $eventUuid,
                'session_uuid' => $session->session_uuid,
                'latitude' => 24.8607,
                'longitude' => 67.0011,
                'recorded_at' => now()->subMinute()->toISOString(),
            ]],
        ];

        $this->withToken($token)->postJson('/api/v1/locations/batch', $payload)->assertOk();
        $response = $this->withToken($token)->postJson('/api/v1/locations/batch', $payload);

        $response->assertOk()->assertJsonPath('data.duplicates', 1);
        $this->assertDatabaseCount('location_logs', 1);
    }

    public function test_expired_session_rejects_location(): void
    {
        [, $device, $session, $token] = $this->activeSession();
        $session->update(['expires_at' => now()->subMinute(), 'status' => TrackingSessionStatus::EXPIRED]);

        $response = $this->withToken($token)->postJson('/api/v1/locations/batch', [
            'device_uuid' => $device->device_uuid,
            'locations' => [[
                'event_uuid' => (string) Str::uuid(),
                'session_uuid' => $session->session_uuid,
                'latitude' => 24.8607,
                'longitude' => 67.0011,
                'recorded_at' => now()->subMinutes(2)->toISOString(),
            ]],
        ]);

        $response->assertOk()->assertJsonPath('data.rejected', 1);
        $this->assertDatabaseCount('location_logs', 0);
    }

    public function test_latest_location_is_not_overwritten_by_older_event(): void
    {
        [$employee, $device, $session, $token] = $this->activeSession();
        $newer = now()->subMinute();
        $older = now()->subMinutes(5);

        LocationLog::factory()->create([
            'employee_id' => $employee->id, 'device_id' => $device->id, 'session_id' => $session->id,
            'recorded_at' => $newer, 'received_at' => $newer,
        ]);
        LatestLocation::factory()->create([
            'employee_id' => $employee->id, 'session_id' => $session->id,
            'latitude' => 1.0000000, 'longitude' => 1.0000000,
            'recorded_at' => $newer, 'received_at' => $newer,
        ]);

        $this->withToken($token)->postJson('/api/v1/locations/batch', [
            'device_uuid' => $device->device_uuid,
            'locations' => [[
                'event_uuid' => (string) Str::uuid(),
                'session_uuid' => $session->session_uuid,
                'latitude' => 9.0000000,
                'longitude' => 9.0000000,
                'recorded_at' => $older->toISOString(),
            ]],
        ])->assertOk();

        $this->assertEquals('1.0000000', LatestLocation::where('employee_id', $employee->id)->first()->latitude);
    }

    public function test_latest_location_update_dispatches_event(): void
    {
        Event::fake([LatestLocationUpdated::class]);
        [, $device, $session, $token] = $this->activeSession();

        $this->withToken($token)->postJson('/api/v1/locations/batch', [
            'device_uuid' => $device->device_uuid,
            'locations' => [[
                'event_uuid' => (string) Str::uuid(),
                'session_uuid' => $session->session_uuid,
                'latitude' => 24.8607,
                'longitude' => 67.0011,
                'recorded_at' => now()->subMinute()->toISOString(),
            ]],
        ])->assertOk();

        Event::assertDispatched(LatestLocationUpdated::class);
    }

    public function test_batch_rejects_foreign_session(): void
    {
        [, $device, , $token] = $this->activeSession();
        $otherSession = TrackingSession::factory()->create();

        $response = $this->withToken($token)->postJson('/api/v1/locations/batch', [
            'device_uuid' => $device->device_uuid,
            'locations' => [[
                'event_uuid' => (string) Str::uuid(),
                'session_uuid' => $otherSession->session_uuid,
                'latitude' => 24.8607,
                'longitude' => 67.0011,
                'recorded_at' => now()->subMinute()->toISOString(),
            ]],
        ]);

        $response->assertOk()->assertJsonPath('data.rejected', 1);
        $this->assertDatabaseCount('location_logs', 0);
    }
}
