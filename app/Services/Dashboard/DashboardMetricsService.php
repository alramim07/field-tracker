<?php

namespace App\Services\Dashboard;

use App\Models\Employee;
use App\Models\LatestLocation;
use App\Models\TrackingSession;
use Illuminate\Support\Carbon;

class DashboardMetricsService
{
    public function summary(?Carbon $now = null): array
    {
        $now = $now ? $now->copy() : now();
        $staleCutoff = $now->copy()->subMinutes(5);

        $employees = Employee::query()
            ->with('user:id,name')
            ->whereNotNull('duty_start_time')
            ->whereNotNull('duty_end_time')
            ->get();

        $activeUserIds = TrackingSession::query()
            ->where('status', 'active')
            ->where('expires_at', '>', $now)
            ->where('started_at', '<=', $now)
            ->pluck('employee_id')
            ->unique();

        $recentLocationEmployeeIds = LatestLocation::query()
            ->where('recorded_at', '>=', $staleCutoff)
            ->pluck('employee_id')
            ->unique();

        // "Currently using app" means an active tracking session with a fresh location ping.
        $usingNow = $activeUserIds->intersect($recentLocationEmployeeIds)->values();

        $late = 0;
        $notUsing = 0;
        $startedInactive = [];

        foreach ($employees as $employee) {
            $start = Carbon::parse($now->toDateString().' '.$employee->duty_start_time);
            $end = Carbon::parse($now->toDateString().' '.$employee->duty_end_time);
            if ($end->lessThanOrEqualTo($start)) {
                $end->addDay();
            }
            $inWindow = $now->betweenIncluded($start, $end);
            if (! $inWindow) {
                continue;
            }

            $isUsing = $usingNow->contains($employee->id);
            if (! $isUsing) {
                $notUsing++;
                if ($now->greaterThanOrEqualTo($start->copy()->addMinutes(15))) {
                    $late++;
                }

                $startedNearDuty = TrackingSession::query()
                    ->where('employee_id', $employee->id)
                    ->whereBetween('started_at', [$start->copy()->subMinutes(5), $start->copy()->addMinutes(30)])
                    ->exists();
                if ($startedNearDuty) {
                    $startedInactive[] = [
                        'employee_id' => $employee->id,
                        'name' => $employee->user?->name,
                        'duty_start_time' => $employee->duty_start_time,
                        'duty_end_time' => $employee->duty_end_time,
                    ];
                }
            }
        }

        return [
            'active_users' => $usingNow->count(),
            'late_to_start' => $late,
            'not_using_tracker' => $notUsing,
            'started_duty_inactive_now' => count($startedInactive),
            'started_duty_inactive_now_list' => $startedInactive,
        ];
    }
}
