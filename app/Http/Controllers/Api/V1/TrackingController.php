<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tracking\StartTrackingRequest;
use App\Models\Device;
use App\Services\Tracking\TrackingSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrackingController extends Controller
{
    public function __construct(private readonly TrackingSessionService $trackingSessionService)
    {
    }

    public function start(StartTrackingRequest $request): JsonResponse
    {
        $employee = $request->user()->employee;
        if (! $employee) {
            return response()->json(['message' => 'Employee profile not found.'], 403);
        }

        $device = Device::query()->where('device_uuid', $request->validated('device_uuid'))->firstOrFail();
        $session = $this->trackingSessionService->start(
            $employee,
            $device,
            $request->ip(),
            $request->validated('mode', 'normal_duty'),
            $request->validated('client_midnight_at'),
            $request->validated('client_timezone'),
            $request->validated('client_now'),
        );

        return response()->json(['data' => $this->sessionPayload($session, $employee)], 201);
    }

    public function stop(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;
        if (! $employee) {
            return response()->json(['message' => 'Employee profile not found.'], 403);
        }

        $session = $this->trackingSessionService->stop($employee, $request->ip());

        return response()->json(['data' => $this->sessionPayload($session, $employee)]);
    }

    public function status(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;
        if (! $employee) {
            return response()->json(['message' => 'Employee profile not found.'], 403);
        }

        $clientMidnightAt = $request->query('client_midnight_at');
        $clientMidnightAt = is_string($clientMidnightAt) && $clientMidnightAt !== '' ? $clientMidnightAt : null;

        $session = $this->trackingSessionService->status($employee);
        $statusMeta = $this->trackingSessionService->statusMeta($employee, $session, $clientMidnightAt);

        return response()->json([
            'data' => [
                'server_time' => now()->toISOString(),
                'active_session' => $session ? $this->sessionPayload($session, $employee, $statusMeta) : null,
                'session_type' => $session?->session_type?->value,
                'counted' => $session?->counted,
                'can_extend' => $statusMeta['can_extend'],
                'has_used_extra_today' => $statusMeta['has_used_extra_today'],
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function sessionPayload(\App\Models\TrackingSession $session, ?\App\Models\Employee $employee = null, ?array $statusMeta = null): array
    {
        $statusMeta ??= $employee ? $this->trackingSessionService->statusMeta($employee, $session) : ['can_extend' => false, 'has_used_extra_today' => false];

        return [
            'session_uuid' => $session->session_uuid,
            'status' => $session->status->value,
            'session_type' => $session->session_type?->value,
            'counted' => $session->counted,
            'can_extend' => $statusMeta['can_extend'],
            'has_used_extra_today' => $statusMeta['has_used_extra_today'],
            'started_at' => $session->started_at?->toISOString(),
            'expires_at' => $session->expires_at?->toISOString(),
            'ended_at' => $session->ended_at?->toISOString(),
            'stop_reason' => $session->stop_reason?->value,
            'device_id' => $session->device_id,
            'tracking_policy_id' => $session->tracking_policy_id,
            'policy_snapshot' => $session->policy_snapshot,
        ];
    }
}
