<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Support\Permissions\PermissionKeys;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PermissionKeys::all() as $permission) {
            Permission::query()->updateOrCreate(['key' => $permission['key']], $permission);
        }
    }
}
