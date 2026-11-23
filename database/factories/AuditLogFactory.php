<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AuditLog> */
class AuditLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'action' => 'test.action',
            'target_type' => 'test',
            'target_id' => 1,
            'metadata' => ['key' => 'value'],
            'ip_address' => fake()->ipv4(),
        ];
    }
}
