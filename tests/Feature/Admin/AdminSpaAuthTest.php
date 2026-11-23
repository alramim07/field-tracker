<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSpaAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_admin_can_login_via_spa_session(): void
    {
        $admin = User::factory()->superAdmin()->create([
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $response = $this->postJson('/api/v1/admin/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.email', 'admin@example.com')
            ->assertJsonMissingPath('data.token');

        $this->getJson('/api/v1/admin/auth/me')->assertOk()
            ->assertJsonPath('data.user.email', 'admin@example.com');
    }

    public function test_employee_cannot_login_to_admin_spa(): void
    {
        User::factory()->create([
            'email' => 'employee@example.com',
            'password' => 'password',
        ]);

        $this->postJson('/api/v1/admin/auth/login', [
            'email' => 'employee@example.com',
            'password' => 'password',
        ])->assertForbidden();
    }

    public function test_existing_mobile_token_login_still_works(): void
    {
        User::factory()->create([
            'email' => 'mobile@example.com',
            'password' => 'password',
        ]);

        $this->postJson('/api/v1/login', [
            'email' => 'mobile@example.com',
            'password' => 'password',
        ])->assertOk()->assertJsonStructure(['data' => ['token', 'user']]);
    }
}
