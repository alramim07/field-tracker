<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_admin_endpoints(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/admin/employees')->assertOk();
        $this->withToken($token)->getJson('/api/v1/admin/devices')->assertOk();
        $this->withToken($token)->getJson('/api/v1/admin/tracking-policies')->assertOk();
        $this->withToken($token)->getJson('/api/v1/admin/audit-logs')->assertOk();
        $this->withToken($token)->getJson('/api/v1/admin/live-locations')->assertOk();
    }

    public function test_employee_cannot_access_admin_endpoints(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/admin/employees')->assertForbidden();
    }
}
