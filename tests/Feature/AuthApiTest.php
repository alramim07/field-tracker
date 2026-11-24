<?php

namespace Tests\Feature;

use App\Enums\StopReason;
use App\Enums\TrackingSessionStatus;
use App\Enums\UserStatus;
use App\Models\Device;
use App\Models\Employee;
use App\Models\TrackingPolicy;
use App\Models\TrackingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create(['password' => 'password123']);

        $response = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'password123',
            'device_name' => 'test-device',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.email', $user->email)
            ->assertJsonStructure(['data' => ['token', 'user' => ['id', 'name', 'email', 'role', 'status']]]);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        $user = User::factory()->create(['password' => 'password123']);

        $response = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->create([
            'password' => 'password123',
            'status' => UserStatus::INACTIVE,
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_authenticated_user_can_get_me_and_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $me = $this->withToken($token)->getJson('/api/v1/me');
        $me->assertOk()->assertJsonPath('data.email', $user->email);

        $logout = $this->withToken($token)->postJson('/api/v1/logout');
        $logout->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'test',
        ]);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_logout_ends_active_tracking_session_before_token_deletion(): void
    {
        Carbon::setTestNow('2026-11-24 09:00:00');
        $employee = Employee::factory()->create([
            'duty_start_time' => '09:00:00',
            'duty_end_time' => '21:00:00',
        ]);
        $device = Device::factory()->create(['employee_id' => $employee->id]);
        $token = $employee->user->createToken('test')->plainTextToken;
        TrackingPolicy::factory()->create(['duration_minutes' => 540]);

        $this->withToken($token)->postJson('/api/v1/tracking/start', [
            'device_uuid' => $device->device_uuid,
        ])->assertCreated();

        $session = TrackingSession::query()->where('employee_id', $employee->id)->firstOrFail();

        $this->withToken($token)->postJson('/api/v1/logout')->assertOk();

        $session->refresh();
        $this->assertSame(TrackingSessionStatus::STOPPED, $session->status);
        $this->assertSame(StopReason::LOGOUT, $session->stop_reason);
        $this->assertNotNull($session->ended_at);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $employee->user->id,
            'name' => 'test',
        ]);
        Carbon::setTestNow();
    }
}
