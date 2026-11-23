<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Area;
use App\Models\Employee;
use App\Models\Region;
use App\Models\TrackingPolicy;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'superadmin@fieldtracker.local',
            'role' => UserRole::SUPER_ADMIN,
            'status' => UserStatus::ACTIVE,
        ]);

        $region = Region::factory()->create([
            'name' => 'Central Region',
            'code' => 'REG-CENTRAL',
        ]);

        $area = Area::factory()->create([
            'region_id' => $region->id,
            'name' => 'Central Area 1',
            'code' => 'AREA-C1',
        ]);

        $employeeUser = User::factory()->create([
            'name' => 'Test Employee',
            'email' => 'employee@fieldtracker.local',
            'role' => UserRole::EMPLOYEE,
            'status' => UserStatus::ACTIVE,
        ]);

        Employee::factory()->create([
            'user_id' => $employeeUser->id,
            'employee_code' => 'EMP-0001',
            'region_id' => $region->id,
            'area_id' => $area->id,
        ]);

        TrackingPolicy::factory()->create([
            'name' => 'Default 9 Hour Duty',
            'duration_minutes' => 540,
            'moving_interval_seconds' => 5,
            'stationary_interval_seconds' => 30,
            'created_by' => $superAdmin->id,
        ]);
    }
}
