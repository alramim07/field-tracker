<?php

namespace Database\Factories;

use App\Models\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Permission>
 */
class PermissionFactory extends Factory
{
    public function definition(): array
    {
        $section = fake()->randomElement(['dashboard', 'live_map', 'tracker_users', 'admins', 'settings']);
        $action = fake()->randomElement(['view', 'create', 'edit', 'delete']);

        return [
            'key' => $section.'.'.$action.'.'.fake()->unique()->numerify('###'),
            'section' => $section,
            'action' => $action,
            'label' => ucfirst($action).' '.str_replace('_', ' ', $section),
        ];
    }
}
