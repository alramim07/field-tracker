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

        $totalSessions = TrackingSession::query()->where('employee_id', $employee->id)->count();
        $activeSessions = TrackingSession::query()->where('employee_id', $employee->id)->where('status', 'active')->count();
        $totalLocations = LocationLog::query()->where('employee_id', $employee->id)->count();

        return response()->json([
            'data' => [
                'total_sessions' => $totalSessions,
                'active_sessions' => $activeSessions,
                'total_locations' => $totalLocations,
            ],
        ]);
    }
}
