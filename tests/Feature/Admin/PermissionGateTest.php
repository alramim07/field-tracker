<?php

namespace Tests\Feature\Admin;

use App\Models\AdminRole;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PermissionGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['auth:sanctum'])->get('/_test/permission-gated', function () {
            \Illuminate\Support\Facades\Gate::authorize('permission', 'dashboard.view');

            return response()->json(['ok' => true]);
        });
    }

    public function test_super_admin_bypasses_granular_permission(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/_test/permission-gated')->assertOk();
    }

    public function test_admin_without_permission_gets_forbidden(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/_test/permission-gated')->assertForbidden();
    }
}
