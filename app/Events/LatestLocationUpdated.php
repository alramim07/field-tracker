<?php

namespace App\Events;

use App\Models\LatestLocation;
use App\Services\LiveMap\LiveUserDataService;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LatestLocationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly LatestLocation $latestLocation) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('admin.live-locations')];
    }

    public function broadcastAs(): string
    {
        return 'live-location.updated';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        $location = $this->latestLocation->loadMissing('employee.user:id,name', 'employee:id,user_id,designation,employee_code,duty_start_time,duty_end_time');
        $employee = $location->employee;
        $details = app(LiveUserDataService::class)->detailsFor($location);

        return [
            'employee_id' => $employee?->id,
            'name' => $employee?->user?->name,
            'designation' => $employee?->designation,
            'avatar_path' => $employee?->user?->avatar_path,
            'latitude' => (float) $location->latitude,
            'longitude' => (float) $location->longitude,
            'accuracy' => $location->accuracy !== null ? (float) $location->accuracy : null,
            'recorded_at' => optional($location->recorded_at)->toISOString(),
            'status' => 'active',
            ...$details,
        ];
    }
}
