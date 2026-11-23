<?php

namespace Tests\Feature\Admin;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_admin_with_permissions_and_designation(): void
    {
        $super = User::factory()->superAdmin()->create();
        $token = $super->createToken('test')->plainTextToken;
        Permission::query()->create(['key' => 'dashboard.view', 'section' => 'dashboard', 'action' => 'view', 'label' => 'View dashboard']);
        Permission::query()->create(['key' => 'tracker_users.view', 'section' => 'tracker_users', 'action' => 'view', 'label' => 'View tracker users']);

        $response = $this->withToken($token)->postJson('/api/v1/admin/admin-users', [
            'name' => 'Ops Admin',
            'email' => 'ops@example.com',
            'password' => 'password123',
            'designation' => 'Operations Manager',
            'permissions' => ['dashboard.view', 'tracker_users.view'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.designation', 'Operations Manager');

        $admin = User::query()->where('email', 'ops@example.com')->firstOrFail();
        $this->assertTrue($admin->hasPermission('dashboard.view'));
        $this->assertSame('Operations Manager', $admin->designation);
    }

    public function test_normal_admin_cannot_create_admin_user(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/admin/admin-users', [
            'name' => 'Another Admin',
            'email' => 'another@example.com',
            'password' => 'password123',
            'designation' => 'Manager',
        ])->assertForbidden();
    }
}
