<?php

namespace Tests\Feature\Admin;

use App\Events\LatestLocationUpdated;
use App\Models\Device;
use App\Models\Employee;
use App\Models\LatestLocation;
use App\Models\LocationLog;
use App\Models\TrackingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class LiveLocationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-24 10:00:00');
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_live_locations_endpoint_returns_latest_location_users(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $user = User::factory()->create(['name' => 'Field One']);
        $employee = Employee::factory()->create(['user_id' => $user->id]);
        LatestLocation::query()->create([
            'employee_id' => $employee->id,
            'latitude' => 23.8103,
            'longitude' => 90.4125,
            'accuracy' => 12.5,
            'recorded_at' => now(),
            'received_at' => now(),
        ]);

        $response = $this->withToken($token)->getJson('/api/v1/admin/live-locations');
        $response->assertOk()->assertJsonPath('data.0.name', 'Field One');
    }

    public function test_live_location_payload_contains_only_safe_fields(): void
    {
        $user = User::factory()->create(['name' => 'Field One', 'email' => 'secret@example.com']);
        $employee = Employee::factory()->create(['user_id' => $user->id]);
        $location = LatestLocation::query()->create([
            'employee_id' => $employee->id,
            'latitude' => 23.8103,
            'longitude' => 90.4125,
            'accuracy' => 12.5,
            'recorded_at' => now(),
            'received_at' => now(),
        ]);

        $event = new LatestLocationUpdated($location);
        $payload = $event->broadcastWith();

        $this->assertArrayHasKey('latitude', $payload);
        $this->assertArrayHasKey('longitude', $payload);
        $this->assertArrayNotHasKey('email', $payload);
        $this->assertArrayNotHasKey('password', $payload);
    }

    public function test_live_location_payload_includes_place_and_duty_durations(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::sequence()
                ->push(['display_name' => 'Current Place, Baridhara, Dhaka, Bangladesh'])
                ->push(['display_name' => 'Previous Place, Uttara, Dhaka, Bangladesh']),
        ]);

        $admin = User::factory()->superAdmin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $user = User::factory()->create(['name' => 'Field One']);
        $employee = Employee::factory()->create([
            'user_id' => $user->id,
            'duty_start_time' => '09:00:00',
            'duty_end_time' => '21:00:00',
        ]);
        $device = Device::factory()->create(['employee_id' => $employee->id]);
        $session = TrackingSession::factory()->create([
            'employee_id' => $employee->id,
            'device_id' => $device->id,
            'status' => 'active',
            'started_at' => '2026-11-24 09:00:00',
            'expires_at' => '2026-11-24 18:00:00',
        ]);

        $pings = [
            '09:00:00' => [23.8103, 90.4125],
            '09:04:00' => [23.8103, 90.4125],
            '09:20:00' => [23.8103, 90.4125],
            '09:25:00' => [23.8000, 90.4000],
            '09:45:00' => [23.8103, 90.4125],
        ];

        foreach ($pings as $time => [$latitude, $longitude]) {
            LocationLog::query()->create([
                'event_uuid' => (string) Str::uuid(),
                'employee_id' => $employee->id,
                'device_id' => $device->id,
                'session_id' => $session->id,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'recorded_at' => '2026-11-24 '.$time,
                'received_at' => '2026-11-24 '.$time,
            ]);
        }

        $location = LatestLocation::query()->create([
            'employee_id' => $employee->id,
            'session_id' => $session->id,
            'latitude' => 23.8103,
            'longitude' => 90.4125,
            'accuracy' => 12.5,
            'recorded_at' => '2026-11-24 09:45:00',
            'received_at' => '2026-11-24 09:45:00',
        ]);

        $response = $this->withToken($token)->getJson('/api/v1/admin/live-locations');

        $response->assertOk()
            ->assertJsonPath('data.0.place_name', 'Current Place, Baridhara, Dhaka, Bangladesh')
            ->assertJsonPath('data.0.tracker_seconds', 3600)
            ->assertJsonPath('data.0.offline_seconds', 1560)
            ->assertJsonPath('data.0.is_tracking', true)
            ->assertJsonPath('data.0.last_location.place_name', 'Previous Place, Uttara, Dhaka, Bangladesh')
            ->assertJsonPath('data.0.last_location.latitude', 23.8)
            ->assertJsonPath('data.0.last_location.recorded_at', '2026-11-24T03:25:00.000000Z');
    }

    public function test_broadcast_payload_includes_updated_last_location_fields(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::sequence()
                ->push(['display_name' => 'Dhaka, Bangladesh'])
                ->push(['display_name' => 'Previous Dhaka, Bangladesh']),
        ]);

        $user = User::factory()->create(['name' => 'Field One']);
        $employee = Employee::factory()->create([
            'user_id' => $user->id,
            'duty_start_time' => '09:00:00',
            'duty_end_time' => '21:00:00',
        ]);
        $device = Device::factory()->create(['employee_id' => $employee->id]);
        $session = TrackingSession::factory()->create([
            'employee_id' => $employee->id,
            'device_id' => $device->id,
        ]);
        LocationLog::query()->create([
            'event_uuid' => (string) Str::uuid(),
            'employee_id' => $employee->id,
            'device_id' => $device->id,
            'session_id' => $session->id,
            'latitude' => 23.8000,
            'longitude' => 90.4000,
            'recorded_at' => '2026-11-24 09:25:00',
            'received_at' => '2026-11-24 09:25:00',
        ]);
        $location = LatestLocation::query()->create([
            'employee_id' => $employee->id,
            'latitude' => 23.8103,
            'longitude' => 90.4125,
            'recorded_at' => '2026-11-24 09:45:00',
            'received_at' => '2026-11-24 09:45:00',
        ]);

        $payload = (new LatestLocationUpdated($location))->broadcastWith();

        $this->assertSame('Dhaka, Bangladesh', $payload['place_name']);
        $this->assertSame('Previous Dhaka, Bangladesh', $payload['last_location']['place_name']);
        $this->assertSame('2026-11-24T03:25:00.000000Z', $payload['last_location']['recorded_at']);
    }

    public function test_latest_location_factory_generates_bangladesh_coordinates(): void
    {
        foreach (range(1, 10) as $i) {
            $location = LatestLocation::factory()->make();

            $this->assertGreaterThanOrEqual(20, (float) $location->latitude);
            $this->assertLessThanOrEqual(27, (float) $location->latitude);
            $this->assertGreaterThanOrEqual(88, (float) $location->longitude);
            $this->assertLessThanOrEqual(92, (float) $location->longitude);
        }
    }
}
