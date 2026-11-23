<?php

namespace Tests\Feature\Admin;

use App\Events\LatestLocationUpdated;
use App\Models\Employee;
use App\Models\LatestLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class LiveLocationApiTest extends TestCase
{
    use RefreshDatabase;

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
}
