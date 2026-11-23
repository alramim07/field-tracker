<?php

namespace Tests\Feature\Admin;

use App\Models\Device;
use App\Models\Employee;
use App\Models\LatestLocation;
use App\Models\TrackingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_summary_returns_expected_metric_keys(): void
    {
        Carbon::setTestNow('2026-11-23 10:00:00');
        $admin = User::factory()->superAdmin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $employeeUser = User::factory()->create();
        $employee = Employee::factory()->create([
            'user_id' => $employeeUser->id,
            'duty_start_time' => '09:00:00',
            'duty_end_time' => '21:00:00',
        ]);
        $device = Device::factory()->create(['employee_id' => $employee->id]);
        TrackingSession::factory()->create([
            'employee_id' => $employee->id,
            'device_id' => $device->id,
            'status' => 'active',
            'started_at' => '2026-11-23 09:05:00',
            'expires_at' => '2026-11-23 18:00:00',
        ]);
        LatestLocation::query()->create([
            'employee_id' => $employee->id,
            'latitude' => 23.8103,
            'longitude' => 90.4125,
            'recorded_at' => '2026-11-23 09:59:00',
            'received_at' => '2026-11-23 09:59:00',
        ]);

        $response = $this->withToken($token)->getJson('/api/v1/admin/dashboard/summary');
        $response->assertOk()
            ->assertJsonStructure(['data' => ['active_users', 'late_to_start', 'not_using_tracker', 'started_duty_inactive_now']]);

        $this->assertSame(1, $response->json('data.active_users'));
    }

    public function test_active_session_without_recent_location_is_not_currently_using_app(): void
    {
        Carbon::setTestNow('2026-11-23 10:00:00');
        $admin = User::factory()->superAdmin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $employeeUser = User::factory()->create();
        $employee = Employee::factory()->create([
            'user_id' => $employeeUser->id,
            'duty_start_time' => '09:00:00',
            'duty_end_time' => '21:00:00',
        ]);
        $device = Device::factory()->create(['employee_id' => $employee->id]);
        TrackingSession::factory()->create([
            'employee_id' => $employee->id,
            'device_id' => $device->id,
            'status' => 'active',
            'started_at' => '2026-11-23 09:05:00',
            'expires_at' => '2026-11-23 18:00:00',
        ]);

        $response = $this->withToken($token)->getJson('/api/v1/admin/dashboard/summary');

        $response->assertOk()->assertJsonPath('data.active_users', 0);
    }
}
