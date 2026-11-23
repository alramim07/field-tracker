<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeviceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_register_device(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create(['user_id' => $user->id]);
        $token = $user->createToken('test')->plainTextToken;

        $payload = [
            'device_uuid' => (string) Str::uuid(),
            'manufacturer' => 'Samsung',
            'model' => 'A54',
            'android_version' => '14',
            'app_version' => '1.0.0',
        ];

        $response = $this->withToken($token)->postJson('/api/v1/device/register', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.device_uuid', $payload['device_uuid'])
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('devices', [
            'device_uuid' => $payload['device_uuid'],
            'employee_id' => $employee->id,
        ]);
    }

    public function test_duplicate_device_registration_for_same_employee_updates_device(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create(['user_id' => $user->id]);
        $token = $user->createToken('test')->plainTextToken;
        $uuid = (string) Str::uuid();

        $this->withToken($token)->postJson('/api/v1/device/register', [
            'device_uuid' => $uuid, 'model' => 'Old',
        ])->assertCreated();

        $this->withToken($token)->postJson('/api/v1/device/register', [
            'device_uuid' => $uuid, 'model' => 'New',
        ])->assertCreated();

        $this->assertDatabaseCount('devices', 1);
        $this->assertDatabaseHas('devices', ['device_uuid' => $uuid, 'model' => 'New']);
    }

    public function test_device_cannot_be_registered_to_another_employee(): void
    {
        $employee1 = Employee::factory()->create();
        $employee2 = Employee::factory()->create();
        $token2 = $employee2->user->createToken('test')->plainTextToken;
        $uuid = (string) Str::uuid();

        Device::factory()->create(['employee_id' => $employee1->id, 'device_uuid' => $uuid]);

        $response = $this->withToken($token2)->postJson('/api/v1/device/register', [
            'device_uuid' => $uuid,
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['device_uuid']);
    }

    public function test_admin_can_revoke_device(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $device = Device::factory()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/v1/device/revoke', [
            'device_uuid' => $device->device_uuid,
        ]);

        $response->assertOk()->assertJsonPath('data.status', 'revoked');
        $this->assertDatabaseHas('devices', ['id' => $device->id, 'status' => 'revoked']);
    }

    public function test_non_admin_cannot_revoke_device(): void
    {
        $user = User::factory()->create();
        $device = Device::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/device/revoke', [
            'device_uuid' => $device->device_uuid,
        ])->assertForbidden();
    }
}
