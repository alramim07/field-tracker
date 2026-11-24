<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\TrackingSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_me_returns_phase3_profile_fields(): void
    {
        $employee = Employee::factory()->create(['phone' => '01700000000', 'duty_start_time' => '09:00:00', 'duty_end_time' => '21:00:00']);
        $token = $employee->user->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.phone', '01700000000')
            ->assertJsonPath('data.duty_start_time', '09:00:00')
            ->assertJsonPath('data.duty_end_time', '21:00:00')
            ->assertJsonStructure(['data' => ['photo_url', 'total_tracking_seconds']]);
    }

    public function test_employee_can_update_profile_but_not_admin_managed_fields(): void
    {
        $employee = Employee::factory()->create();
        $token = $employee->user->createToken('test')->plainTextToken;

        $this->withToken($token)->patchJson('/api/v1/me/profile', [
            'phone' => '01811111111',
            'email' => 'new@example.com',
            'name' => 'Hack',
            'employee_code' => 'X',
        ])->assertOk();

        $employee->refresh()->load('user');
        $this->assertSame('01811111111', $employee->phone);
        $this->assertSame('new@example.com', $employee->user->email);
        $this->assertNotSame('Hack', $employee->user->name);
    }

    public function test_me_total_tracking_seconds_counts_only_counted_sessions(): void
    {
        $employee = Employee::factory()->create();
        $token = $employee->user->createToken('test')->plainTextToken;

        TrackingSession::factory()->for($employee)->create([
            'started_at' => '2026-11-24 09:00:00',
            'ended_at' => '2026-11-24 10:00:00',
            'counted' => true,
        ]);
        TrackingSession::factory()->for($employee)->create([
            'started_at' => '2026-11-24 11:00:00',
            'ended_at' => '2026-11-24 12:30:00',
            'counted' => false,
        ]);

        $response = $this->withToken($token)->getJson('/api/v1/me')->assertOk();
        $this->assertSame(3600, $response->json('data.total_tracking_seconds'));
    }

    public function test_employee_can_upload_profile_photo(): void
    {
        Storage::fake('public');
        $employee = Employee::factory()->create();
        $token = $employee->user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->post('/api/v1/me/photo', [
            'photo' => UploadedFile::fake()->image('avatar.jpg'),
        ], ['Accept' => 'application/json']);

        $response->assertOk()->assertJsonStructure(['data' => ['photo_url']]);

        $employee->refresh()->load('user');
        $this->assertNotNull($employee->user->avatar_path);
        Storage::disk('public')->assertExists($employee->user->avatar_path);
        $this->assertNotNull($response->json('data.photo_url'));
    }

    public function test_employee_can_change_password_and_other_tokens_are_revoked(): void
    {
        $employee = Employee::factory()->create();
        $user = $employee->user;
        $user->update(['password' => 'old-password']);

        $currentToken = $user->createToken('current')->plainTextToken;
        $otherToken = $user->createToken('other');
        $otherTokenId = $otherToken->accessToken->id;

        $response = $this->withToken($currentToken)->postJson('/api/v1/me/password', [
            'current_password' => 'old-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response->assertOk();

        $user->refresh();
        $this->assertTrue(Hash::check('new-password-123', $user->password));
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $otherTokenId]);

        $this->withToken($currentToken)->getJson('/api/v1/me')->assertOk();
    }
}
