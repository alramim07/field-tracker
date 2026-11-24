<?php

namespace App\Console\Commands;

use App\Enums\TrackingSessionStatus;
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
                $session->markAsExpired();
            });

        return self::SUCCESS;
    }
}
