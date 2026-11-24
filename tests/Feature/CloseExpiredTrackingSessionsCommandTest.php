<?php

namespace Tests\Feature;

use App\Enums\StopReason;
use App\Enums\TrackingSessionStatus;
use App\Enums\TrackingSessionType;
use App\Models\TrackingSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CloseExpiredTrackingSessionsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_closes_forgotten_extra_duty_as_uncounted(): void
    {
        Carbon::setTestNow('2026-11-24 23:59:30');
        $session = TrackingSession::factory()->create([
            'session_type' => TrackingSessionType::EXTRA_DUTY,
            'status' => TrackingSessionStatus::ACTIVE,
            'counted' => true,
            'started_at' => '2026-11-24 21:10:00',
            'expires_at' => '2026-11-24 23:59:59',
        ]);
        Carbon::setTestNow('2026-11-25 00:00:05');
        $this->artisan('tracking:close-expired')->assertExitCode(0);
        $session->refresh();
        $this->assertSame(TrackingSessionStatus::EXPIRED, $session->status);
        $this->assertSame(StopReason::MIDNIGHT_AUTO, $session->stop_reason);
        $this->assertFalse($session->counted);
    }

    public function test_command_closes_expired_normal_session(): void
    {
        Carbon::setTestNow('2026-11-24 21:00:00');
        $session = TrackingSession::factory()->create([
            'session_type' => TrackingSessionType::NORMAL_DUTY,
            'status' => TrackingSessionStatus::ACTIVE,
            'counted' => true,
            'started_at' => '2026-11-24 09:00:00',
            'expires_at' => '2026-11-24 21:00:00',
        ]);
        Carbon::setTestNow('2026-11-24 21:00:05');
        $this->artisan('tracking:close-expired')->assertExitCode(0);
        $session->refresh();
        $this->assertSame(TrackingSessionStatus::EXPIRED, $session->status);
        $this->assertSame(StopReason::EXPIRED, $session->stop_reason);
        $this->assertTrue($session->counted);
        $this->assertEquals(Carbon::parse('2026-11-24 21:00:00'), $session->ended_at);
    }

    public function test_command_is_idempotent(): void
    {
        Carbon::setTestNow('2026-11-25 00:00:05');
        $session = TrackingSession::factory()->create([
            'session_type' => TrackingSessionType::EXTRA_DUTY,
            'status' => TrackingSessionStatus::EXPIRED,
            'counted' => false,
            'started_at' => '2026-11-24 21:10:00',
            'expires_at' => '2026-11-24 23:59:59',
            'ended_at' => '2026-11-24 23:59:59',
            'stop_reason' => StopReason::MIDNIGHT_AUTO,
        ]);

        $this->artisan('tracking:close-expired')->assertExitCode(0);
        $session->refresh();
        $this->assertSame(TrackingSessionStatus::EXPIRED, $session->status);
        $this->assertSame(StopReason::MIDNIGHT_AUTO, $session->stop_reason);
        $this->assertFalse($session->counted);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
