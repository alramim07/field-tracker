<?php

namespace Tests\Feature\Admin;

use App\Models\AdminRole;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackerUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_tracker_user_with_duty_time(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/v1/admin/tracker-users', [
            'name' => 'Field User',
            'email' => 'field@example.com',
            'password' => 'password',
            'employee_code' => 'EMP-1001',
            'duty_start_time' => '09:00',
            'duty_end_time' => '21:00',
        ]);

        $response->assertCreated();
        $employee = \App\Models\Employee::query()->where('employee_code', 'EMP-1001')->firstOrFail();
        $this->assertStringStartsWith('09:00', (string) $employee->duty_start_time);
        $this->assertStringStartsWith('21:00', (string) $employee->duty_end_time);
    }

    public function test_admin_without_tracker_users_create_permission_gets_403(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/admin/tracker-users', [
            'name' => 'Field User',
            'email' => 'field2@example.com',
            'password' => 'password',
            'employee_code' => 'EMP-1002',
        ])->assertForbidden();
    }

    public function test_tracker_user_create_requires_duty_times(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/admin/tracker-users', [
            'name' => 'Field User',
            'email' => 'field@example.com',
            'password' => 'password',
            'employee_code' => 'EMP-1001',
        ])->assertUnprocessable()->assertJsonValidationErrors(['duty_start_time', 'duty_end_time']);
    }
}
