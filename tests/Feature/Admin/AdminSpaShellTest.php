<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;

class AdminSpaShellTest extends TestCase
{
    public function test_admin_shell_route_returns_ok(): void
    {
        $this->get('/admin')->assertOk();
    }
}
