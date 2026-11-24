<?php

namespace Tests\Feature\Admin;

use App\Enums\TrackingSessionStatus;
use App\Enums\TrackingSessionType;
use App\Models\Employee;
use App\Models\LocationLog;
use App\Models\TrackingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackerUserHistoryReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_tracker_user_sessions_history_and_report(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $employee = Employee::factory()->create();

        $session = TrackingSession::factory()->create([
            'employee_id' => $employee->id,
            'session_type' => TrackingSessionType::NORMAL_DUTY,
            'status' => TrackingSessionStatus::STOPPED,
            'counted' => true,
            'started_at' => now()->subHours(2),
            'ended_at' => now()->subHour(),
        ]);

        LocationLog::factory()->create([
            'employee_id' => $employee->id,
            'session_id' => $session->id,
            'device_id' => $session->device_id,
            'recorded_at' => now()->subMinutes(90),
        ]);

        $this->actingAs($admin)
            ->getJson('/api/v1/admin/tracker-users/'.$employee->user_id.'/sessions')
            ->assertOk()
            ->assertJsonPath('total', 1);

        $this->actingAs($admin)
            ->getJson('/api/v1/admin/tracker-users/'.$employee->user_id.'/location-history')
            ->assertOk()
            ->assertJsonPath('total', 1);

        $this->actingAs($admin)
            ->getJson('/api/v1/admin/tracker-users/'.$employee->user_id.'/report')
            ->assertOk()
            ->assertJsonPath('data.total_sessions', 1)
            ->assertJsonPath('data.counted_sessions', 1)
            ->assertJsonPath('data.uncounted_sessions', 0)
            ->assertJsonPath('data.total_locations', 1);
    }

    public function test_non_admin_cannot_view_tracker_user_history(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/admin/tracker-users/'.$employee->user_id.'/sessions')
            ->assertForbidden();
    }
}
