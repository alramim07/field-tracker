<?php

namespace App\Services\LiveMap;

use App\Models\LatestLocation;
use App\Models\LocationLog;
use App\Models\TrackingSession;
use Illuminate\Support\Carbon;

class LiveUserDataService
{
    private const OFFLINE_GRACE_SECONDS = 300;

    public function __construct(private readonly PlaceNameResolver $places) {}

    /** @return array<string, mixed> */
    public function detailsFor(LatestLocation $location, ?Carbon $now = null): array
    {
        $now = $now ? $now->copy() : now();
        $location->loadMissing([
            'employee.user:id,name,avatar_path',
            'employee:id,user_id,designation,employee_code,duty_start_time,duty_end_time',
        ]);

        $employee = $location->employee;
        [$windowStart, $windowEnd] = $this->dutyWindow($employee, $now);
        $clipEnd = $now->lessThan($windowEnd) ? $now->copy() : $windowEnd->copy();

        $previous = $this->previousPing($employee->id, $location);

        return [
            'place_name' => $this->places->resolve((float) $location->latitude, (float) $location->longitude),
            'last_location' => [
                'place_name' => $previous
                    ? $this->places->resolve((float) $previous->latitude, (float) $previous->longitude)
                    : null,
                'latitude' => $previous ? (float) $previous->latitude : null,
                'longitude' => $previous ? (float) $previous->longitude : null,
                'recorded_at' => $previous?->recorded_at?->toISOString(),
            ],
            'tracker_seconds' => $this->trackerSeconds($employee->id, $windowStart, $clipEnd),
            'offline_seconds' => $this->offlineSeconds($employee->id, $windowStart, $clipEnd),
            'is_tracking' => $this->isTracking($employee->id, $now),
        ];
    }

    private function previousPing(int $employeeId, LatestLocation $location): ?LocationLog
    {
        if (! $location->recorded_at) {
            return null;
        }

        return LocationLog::query()
            ->where('employee_id', $employeeId)
            ->where('recorded_at', '<', $location->recorded_at)
            ->orderByDesc('recorded_at')
            ->first(['latitude', 'longitude', 'recorded_at']);
    }

    private function isTracking(int $employeeId, Carbon $now): bool
    {
        return TrackingSession::query()
            ->where('employee_id', $employeeId)
            ->where('status', 'active')
            ->where('started_at', '<=', $now)
            ->where('expires_at', '>', $now)
            ->exists();
    }

    /** @return array{Carbon, Carbon} */
    private function dutyWindow(mixed $employee, Carbon $now): array
    {
        $start = Carbon::parse($now->toDateString().' '.$employee->duty_start_time);
        $end = Carbon::parse($now->toDateString().' '.$employee->duty_end_time);

        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        return [$start, $end];
    }

    private function trackerSeconds(int $employeeId, Carbon $windowStart, Carbon $windowEnd): int
    {
        if ($windowEnd->lessThanOrEqualTo($windowStart)) {
            return 0;
        }

        $sessions = TrackingSession::query()
            ->where('employee_id', $employeeId)
            ->where('started_at', '<=', $windowEnd)
            ->where(function ($query) use ($windowStart): void {
                $query->whereNull('ended_at')->orWhere('ended_at', '>', $windowStart);
            })
            ->get(['started_at', 'ended_at', 'expires_at']);

        $seconds = 0;
        foreach ($sessions as $session) {
            $start = $session->started_at->greaterThan($windowStart)
                ? $session->started_at->copy()
                : $windowStart->copy();

            $candidateEnd = $session->ended_at ?? $session->expires_at ?? $windowEnd;
            $end = $candidateEnd->lessThan($windowEnd) ? $candidateEnd->copy() : $windowEnd->copy();

            if ($end->greaterThan($start)) {
                $seconds += $start->diffInSeconds($end);
            }
        }

        return $seconds;
    }

    private function offlineSeconds(int $employeeId, Carbon $windowStart, Carbon $windowEnd): int
    {
        if ($windowEnd->lessThanOrEqualTo($windowStart)) {
            return 0;
        }

        $logs = LocationLog::query()
            ->where('employee_id', $employeeId)
            ->whereBetween('recorded_at', [$windowStart, $windowEnd])
            ->orderBy('recorded_at')
            ->get(['session_id', 'recorded_at']);

        $seconds = 0;
        $previous = null;

        foreach ($logs as $log) {
            if ($previous && $previous->session_id === $log->session_id) {
                $gap = (int) abs($log->recorded_at->diffInSeconds($previous->recorded_at));
                if ($gap > self::OFFLINE_GRACE_SECONDS) {
                    $seconds += $gap - self::OFFLINE_GRACE_SECONDS;
                }
            }

            $previous = $log;
        }

        return $seconds;
    }
}
