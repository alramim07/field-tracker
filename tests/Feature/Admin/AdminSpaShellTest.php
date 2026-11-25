<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;

class AdminSpaShellTest extends TestCase
{
    public function test_admin_shell_route_returns_ok(): void
    {
        $this->get('/admin')->assertOk();
    }

    public function test_admin_shell_loads_built_admin_assets(): void
    {
        $this->get('/admin')
            ->assertOk()
            ->assertDontSee('http://localhost:5174', false)
            ->assertSee('/build-admin/assets/', false);
    }
}
