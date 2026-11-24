<?php

namespace Tests\Feature\Admin;

use App\Enums\StopReason;
use App\Enums\TrackingSessionStatus;
use App\Enums\TrackingSessionType;
use App\Models\Employee;
use App\Models\LocationLog;
use App\Models\TrackingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackerUserAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_tracker_user_details_include_forgotten_extra_duty_audit(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $employee = Employee::factory()->create();

        $session = TrackingSession::factory()->create([
            'employee_id' => $employee->id,
            'session_type' => TrackingSessionType::EXTRA_DUTY,
            'counted' => false,
            'status' => TrackingSessionStatus::EXPIRED,
            'stop_reason' => StopReason::MIDNIGHT_AUTO,
            'started_at' => now()->subHours(2),
            'ended_at' => now()->subHour(),
        ]);

        LocationLog::factory()->create([
            'employee_id' => $employee->id,
            'session_id' => $session->id,
            'latitude' => 23.8103,
            'longitude' => 90.4125,
            'recorded_at' => now()->subMinutes(90),
        ]);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/tracker-users/'.$employee->user_id);

        $response->assertOk()
            ->assertJsonPath('data.extra_duty_audit.0.counted', false)
            ->assertJsonPath('data.extra_duty_audit.0.session_uuid', $session->session_uuid)
            ->assertJsonPath('data.extra_duty_audit.0.stop_reason', StopReason::MIDNIGHT_AUTO->value)
            ->assertJsonStructure(['data' => ['extra_duty_audit' => [['session_uuid', 'started_at', 'ended_at', 'stop_reason', 'duration_seconds', 'locations']]]]);
    }
}
