<?php

namespace Tests\Feature;

use App\Enums\StopReason;
use App\Enums\TrackingSessionStatus;
use App\Enums\TrackingSessionType;
use App\Models\TrackingSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackingSessionModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_tracking_session_supports_phase3_type_counted_and_stop_reasons(): void
    {
        $session = TrackingSession::factory()->create([
            'session_type' => TrackingSessionType::EXTRA_DUTY->value,
            'counted' => false,
            'stop_reason' => StopReason::MIDNIGHT_AUTO->value,
            'status' => TrackingSessionStatus::STOPPED,
        ]);

        $session->refresh();
        $this->assertSame(TrackingSessionType::EXTRA_DUTY, $session->session_type);
        $this->assertFalse($session->counted);
        $this->assertSame(StopReason::MIDNIGHT_AUTO, $session->stop_reason);
    }
}
