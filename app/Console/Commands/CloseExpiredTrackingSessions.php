<?php

namespace App\Console\Commands;

use App\Enums\StopReason;
use App\Enums\TrackingSessionStatus;
use App\Enums\TrackingSessionType;
use App\Models\TrackingSession;
use Illuminate\Console\Command;

class CloseExpiredTrackingSessions extends Command
{
    protected $signature = 'tracking:close-expired';

    protected $description = 'Close tracking sessions that have passed their expiry time';

    public function handle(): int
    {
        TrackingSession::query()
            ->where('status', TrackingSessionStatus::ACTIVE)
            ->where('expires_at', '<=', now())
            ->each(function (TrackingSession $session): void {
                if ($session->session_type === TrackingSessionType::EXTRA_DUTY) {
                    $session->update([
                        'status' => TrackingSessionStatus::EXPIRED,
                        'stop_reason' => StopReason::MIDNIGHT_AUTO,
                        'counted' => false,
                        'ended_at' => $session->expires_at,
                    ]);

                    return;
                }

                $session->update([
                    'status' => TrackingSessionStatus::EXPIRED,
                    'stop_reason' => StopReason::EXPIRED,
                    'ended_at' => $session->expires_at,
                ]);
            });

        return self::SUCCESS;
    }
}
