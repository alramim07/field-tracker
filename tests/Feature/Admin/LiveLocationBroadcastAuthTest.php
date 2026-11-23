<?php

namespace Tests\Feature\Admin;

use App\Models\AdminRole;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Tests\TestCase;

class LiveLocationBroadcastAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_live_location_channel_requires_live_map_view_permission(): void
    {
        $admin = User::factory()->admin()->create();
        $channels = Broadcast::getChannels();
        $this->assertArrayHasKey('admin.live-locations', $channels->toArray());
        $callback = $channels->get('admin.live-locations');
        $this->assertFalse((bool) $callback($admin));
    }

    public function test_live_location_channel_allows_authorized_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $perm = Permission::query()->create(['key' => 'live_map.view', 'section' => 'live_map', 'action' => 'view', 'label' => 'View live map']);
        $role = AdminRole::factory()->create();
        $role->permissions()->attach($perm);
        $admin->adminRoles()->attach($role);

        $callback = Broadcast::getChannels()->get('admin.live-locations');
        $this->assertTrue((bool) $callback($admin));
    }
}
