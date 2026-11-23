<?php

namespace Tests\Feature\Admin;

use App\Models\AdminRole;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbacSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_role_can_be_assigned_permissions(): void
    {
        $role = AdminRole::factory()->create(['slug' => 'ops-admin']);
        $perm = Permission::factory()->create(['key' => 'dashboard.view']);
        $role->permissions()->attach($perm);

        $this->assertTrue($role->permissions->contains('key', 'dashboard.view'));
    }
}
