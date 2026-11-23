<?php

namespace Tests\Feature;

use App\Enums\DeviceStatus;
use App\Enums\StopReason;
use App\Enums\TrackingSessionStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Area;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\Employee;
use App\Models\LatestLocation;
use App\Models\LocationLog;
use App\Models\Region;
use App\Models\TrackingPolicy;
use App\Models\TrackingSession;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase1FoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_core_models_and_relationships_work(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $region = Region::factory()->create();
        $area = Area::factory()->create(['region_id' => $region->id]);
        $employeeUser = User::factory()->create(['role' => UserRole::EMPLOYEE, 'status' => UserStatus::ACTIVE]);
        $employee = Employee::factory()->create([
            'user_id' => $employeeUser->id,
            'region_id' => $region->id,
            'area_id' => $area->id,
        ]);
        $device = Device::factory()->create(['employee_id' => $employee->id, 'status' => DeviceStatus::ACTIVE]);
        $policy = TrackingPolicy::factory()->create(['created_by' => $admin->id]);

        $session = TrackingSession::factory()->create([
            'session_uuid' => (string) Str::uuid(),
            'employee_id' => $employee->id,
            'device_id' => $device->id,
            'tracking_policy_id' => $policy->id,
            'policy_snapshot' => $policy->toArray(),
            'started_at' => now(),
            'expires_at' => now()->addMinutes(540),
            'status' => TrackingSessionStatus::ACTIVE,
            'stop_reason' => null,
        ]);

        $log = LocationLog::factory()->create([
            'employee_id' => $employee->id,
            'device_id' => $device->id,
            'session_id' => $session->id,
        ]);

        $latest = LatestLocation::factory()->create([
            'employee_id' => $employee->id,
            'session_id' => $session->id,
        ]);

        $this->assertTrue($admin->isSuperAdmin());
        $this->assertSame($employee->id, $employeeUser->employee->id);
        $this->assertSame($region->id, $area->region->id);
        $this->assertCount(1, $employee->devices);
        $this->assertCount(1, $employee->trackingSessions);
        $this->assertCount(1, $session->locationLogs);
        $this->assertSame($latest->id, $employee->latestLocation->id);
        $this->assertSame($log->id, $session->locationLogs->first()->id);
    }

    public function test_unique_constraints_are_enforced(): void
    {
        $employee = Employee::factory()->create();
        $session = TrackingSession::factory()->create(['employee_id' => $employee->id]);

        LatestLocation::factory()->create(['employee_id' => $employee->id, 'session_id' => $session->id]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        LatestLocation::factory()->create(['employee_id' => $employee->id, 'session_id' => $session->id]);
    }

    public function test_enum_casts_work(): void
    {
        $session = TrackingSession::factory()->create([
            'status' => TrackingSessionStatus::EXPIRED,
            'stop_reason' => StopReason::EXPIRED,
        ]);

        $session->refresh();

        $this->assertSame(TrackingSessionStatus::EXPIRED, $session->status);
        $this->assertSame(StopReason::EXPIRED, $session->stop_reason);
    }

    public function test_audit_log_service_writes_record(): void
    {
        $user = User::factory()->superAdmin()->create();
        $service = app(AuditLogService::class);

        $log = $service->log('device.revoked', $user, $user, ['reason' => 'test'], '127.0.0.1');

        $this->assertDatabaseHas('audit_logs', [
            'id' => $log->id,
            'user_id' => $user->id,
            'action' => 'device.revoked',
            'target_type' => $user->getMorphClass(),
            'target_id' => $user->id,
            'ip_address' => '127.0.0.1',
        ]);
        $this->assertSame(['reason' => 'test'], $log->fresh()->metadata);
    }
}
