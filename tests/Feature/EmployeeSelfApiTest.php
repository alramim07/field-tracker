<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LocationLog;
use App\Models\TrackingSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeSelfApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_view_own_history_sessions_and_reports(): void
    {
        $employee = Employee::factory()->create();
        $session = TrackingSession::factory()->create(['employee_id' => $employee->id]);
        LocationLog::factory()->create(['employee_id' => $employee->id, 'session_id' => $session->id, 'device_id' => $session->device_id]);
        $token = $employee->user->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/me/sessions')->assertOk()->assertJsonPath('total', 1);
        $this->withToken($token)->getJson('/api/v1/me/location-history')->assertOk()->assertJsonPath('total', 1);
        $this->withToken($token)->getJson('/api/v1/me/reports')->assertOk()->assertJsonPath('data.total_sessions', 1);
    }

    public function test_employee_cannot_see_other_employee_data(): void
    {
        $e1 = Employee::factory()->create();
        $e2 = Employee::factory()->create();
        $session = TrackingSession::factory()->create(['employee_id' => $e2->id]);
        LocationLog::factory()->create(['employee_id' => $e2->id, 'session_id' => $session->id, 'device_id' => $session->device_id]);
        $token = $e1->user->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/me/location-history')->assertOk()->assertJsonPath('total', 0);
        $this->withToken($token)->getJson('/api/v1/me/sessions')->assertOk()->assertJsonPath('total', 0);
    }
}
