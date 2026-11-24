<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LocationLog;
use App\Models\TrackingSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeSelfController extends Controller
{
    public function locationHistory(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;
        if (! $employee) {
            return response()->json(['message' => 'Employee profile not found.'], 403);
        }

        $query = LocationLog::query()->where('employee_id', $employee->id);

        if ($request->filled('session_uuid')) {
            $query->whereHas('session', fn ($q) => $q->where('session_uuid', $request->string('session_uuid')));
        }
        if ($request->filled('from')) {
            $query->where('recorded_at', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $query->where('recorded_at', '<=', $request->date('to'));
        }

        $logs = $query->latest('recorded_at')->paginate(min((int) $request->integer('per_page', 50), 200));

        return response()->json($logs);
    }

    public function sessions(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;
        if (! $employee) {
            return response()->json(['message' => 'Employee profile not found.'], 403);
        }

        $sessions = TrackingSession::query()
            ->where('employee_id', $employee->id)
            ->latest('started_at')
            ->paginate(min((int) $request->integer('per_page', 20), 100));

        return response()->json($sessions);
    }

    public function reports(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;
        if (! $employee) {
            return response()->json(['message' => 'Employee profile not found.'], 403);
        }

        $sessionsQuery = TrackingSession::query()->where('employee_id', $employee->id);
        $totalSessions = (clone $sessionsQuery)->count();
        $activeSessions = (clone $sessionsQuery)->where('status', 'active')->count();
        $countedSessions = (clone $sessionsQuery)->where('counted', true)->count();
        $uncountedSessions = (clone $sessionsQuery)->where('counted', false)->count();
        $totalLocations = LocationLog::query()->where('employee_id', $employee->id)->count();
        $driver = $employee->getConnection()->getDriverName();
        $durationExpression = match ($driver) {
            'sqlite' => "COALESCE(SUM(CAST(ROUND((julianday(ended_at) - julianday(started_at)) * 86400) AS INTEGER)), 0)",
            default => "COALESCE(SUM(TIMESTAMPDIFF(SECOND, started_at, ended_at)), 0)",
        };
        $totalTrackingSeconds = (int) ((clone $sessionsQuery)
            ->where('counted', true)
            ->whereNotNull('ended_at')
            ->selectRaw("{$durationExpression} as total_seconds")
            ->value('total_seconds') ?? 0);

        return response()->json([
            'data' => [
                'total_sessions' => $totalSessions,
                'active_sessions' => $activeSessions,
                'counted_sessions' => $countedSessions,
                'uncounted_sessions' => $uncountedSessions,
                'total_locations' => $totalLocations,
                'total_tracking_seconds' => $totalTrackingSeconds,
            ],
        ]);
    }
}
