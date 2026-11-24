<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\TrackingSessionType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTrackerUserRequest;
use App\Http\Requests\Admin\UpdateTrackerUserRequest;
use App\Http\Resources\Admin\TrackerUserResource;
use App\Models\LocationLog;
use App\Models\TrackingSession;
use App\Models\User;
use App\Services\Admin\TrackerUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrackerUserController extends Controller
{
    public function __construct(private readonly TrackerUserService $service) {}

    public function index(Request $request): JsonResponse
    {
        $users = User::query()->where('role', UserRole::EMPLOYEE)
            ->with('employee')
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return TrackerUserResource::collection($users)->response();
    }

    public function store(StoreTrackerUserRequest $request): JsonResponse
    {
        $user = $this->service->create($request->validated(), $request->user());

        return (new TrackerUserResource($user))->response()->setStatusCode(201);
    }

    public function show(User $tracker_user): JsonResponse
    {
        $tracker_user->load('employee');

        $employee = $tracker_user->employee;
        $audit = [];

        if ($employee) {
            $sessions = TrackingSession::query()
                ->where('employee_id', $employee->id)
                ->where('session_type', TrackingSessionType::EXTRA_DUTY)
                ->where('counted', false)
                ->with(['locationLogs' => fn ($query) => $query->orderBy('recorded_at')])
                ->orderByDesc('started_at')
                ->get();

            $audit = $sessions->map(function (TrackingSession $session): array {
                $startedAt = $session->started_at;
                $endedAt = $session->ended_at;
                $durationSeconds = ($startedAt && $endedAt) ? max(0, $startedAt->diffInSeconds($endedAt)) : null;

                return [
                    'session_uuid' => $session->session_uuid,
                    'started_at' => optional($startedAt)->toISOString(),
                    'ended_at' => optional($endedAt)->toISOString(),
                    'stop_reason' => $session->stop_reason?->value ?? $session->stop_reason,
                    'duration_seconds' => $durationSeconds,
                    'counted' => (bool) $session->counted,
                    'locations' => $session->locationLogs->map(fn ($log): array => [
                        'id' => $log->id,
                        'latitude' => (float) $log->latitude,
                        'longitude' => (float) $log->longitude,
                        'accuracy' => $log->accuracy !== null ? (float) $log->accuracy : null,
                        'recorded_at' => optional($log->recorded_at)->toISOString(),
                    ])->values()->all(),
                ];
            })->values()->all();
        }

        $tracker_user->setAttribute('extra_duty_audit', $audit);

        return (new TrackerUserResource($tracker_user))->response();
    }

    public function update(UpdateTrackerUserRequest $request, User $tracker_user): JsonResponse
    {
        $user = $this->service->update($tracker_user, $request->validated(), $request->user());

        return (new TrackerUserResource($user))->response();
    }

    public function sessions(Request $request, User $tracker_user): JsonResponse
    {
        $employee = $tracker_user->employee;
        if (! $employee) {
            return response()->json(['message' => 'Employee profile not found.'], 404);
        }

        $query = TrackingSession::query()->where('employee_id', $employee->id);
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('from')) {
            $query->where('started_at', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $query->where('started_at', '<=', $request->date('to'));
        }

        $sessions = $query->latest('started_at')->paginate(min((int) $request->integer('per_page', 20), 100));

        return response()->json($sessions);
    }

    public function locationHistory(Request $request, User $tracker_user): JsonResponse
    {
        $employee = $tracker_user->employee;
        if (! $employee) {
            return response()->json(['message' => 'Employee profile not found.'], 404);
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

    public function report(Request $request, User $tracker_user): JsonResponse
    {
        $employee = $tracker_user->employee;
        if (! $employee) {
            return response()->json(['message' => 'Employee profile not found.'], 404);
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

    public function destroy(Request $request, User $tracker_user): JsonResponse
    {
        $this->service->delete($tracker_user, $request->user());

        return response()->json(['message' => 'Deleted.']);
    }
}
