<?php

namespace App\Services\Tracking;

use App\Enums\DeviceStatus;
use App\Enums\StopReason;
use App\Enums\TrackingPolicyStatus;
use App\Enums\TrackingSessionStatus;
use App\Models\Device;
use App\Models\Employee;
use App\Models\TrackingPolicy;
use App\Models\TrackingSession;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TrackingSessionService
{
    public function __construct(private readonly AuditLogService $auditLogService)
    {
    }

    public function start(Employee $employee, Device $device, ?string $ipAddress = null): TrackingSession
    {
        if ($device->employee_id !== $employee->id) {
            throw ValidationException::withMessages(['device_uuid' => ['Device does not belong to this employee.']]);
        }

        if ($device->status !== DeviceStatus::ACTIVE) {
            throw ValidationException::withMessages(['device_uuid' => ['Device is not active.']]);
        }

        $policy = TrackingPolicy::query()
            ->where('status', TrackingPolicyStatus::ACTIVE)
            ->latest('id')
            ->first();

        if (! $policy) {
            throw ValidationException::withMessages(['policy' => ['No active tracking policy configured.']]);
        }

        return DB::transaction(function () use ($employee, $device, $policy, $ipAddress): TrackingSession {
            $active = TrackingSession::query()
                ->where('employee_id', $employee->id)
                ->where('status', TrackingSessionStatus::ACTIVE)
                ->lockForUpdate()
                ->first();

            if ($active) {
                if ($active->expires_at->isPast()) {
                    $active->update(['status' => TrackingSessionStatus::EXPIRED, 'ended_at' => $active->expires_at, 'stop_reason' => StopReason::EXPIRED]);
                } else {
                    throw ValidationException::withMessages(['session' => ['An active tracking session already exists.']]);
                }
            }

            $startedAt = now();
            $expiresAt = $startedAt->copy()->addMinutes($policy->duration_minutes);

            $session = TrackingSession::query()->create([
                'session_uuid' => (string) Str::uuid(),
                'employee_id' => $employee->id,
                'device_id' => $device->id,
                'tracking_policy_id' => $policy->id,
                'policy_snapshot' => $policy->only([
                    'id', 'name', 'start_time', 'end_time', 'duration_minutes',
                    'moving_interval_seconds', 'stationary_interval_seconds',
                ]),
                'started_at' => $startedAt,
                'expires_at' => $expiresAt,
                'status' => TrackingSessionStatus::ACTIVE,
            ]);

            $this->auditLogService->log('tracking.session_started', $employee->user, $session, [
                'device_id' => $device->id,
                'policy_id' => $policy->id,
            ], $ipAddress);

            return $session;
        });
    }

    public function stop(Employee $employee, ?string $ipAddress = null): TrackingSession
    {
        $session = TrackingSession::query()
            ->where('employee_id', $employee->id)
            ->where('status', TrackingSessionStatus::ACTIVE)
            ->latest('started_at')
            ->first();

        if (! $session) {
            throw ValidationException::withMessages(['session' => ['No active tracking session found.']]);
        }

        if ($session->expires_at->isPast()) {
            $session->update(['status' => TrackingSessionStatus::EXPIRED, 'ended_at' => $session->expires_at, 'stop_reason' => StopReason::EXPIRED]);
            throw ValidationException::withMessages(['session' => ['Tracking session has already expired.']]);
        }

        $session->update([
            'status' => TrackingSessionStatus::STOPPED,
            'ended_at' => now(),
            'stop_reason' => StopReason::MANUAL,
        ]);

        $this->auditLogService->log('tracking.session_stopped', $employee->user, $session, [], $ipAddress);

        return $session->fresh();
    }

    public function status(Employee $employee): ?TrackingSession
    {
        $session = TrackingSession::query()
            ->where('employee_id', $employee->id)
            ->latest('started_at')
            ->first();

        if ($session && $session->status === TrackingSessionStatus::ACTIVE && $session->expires_at->isPast()) {
            $session->update([
                'status' => TrackingSessionStatus::EXPIRED,
                'ended_at' => $session->expires_at,
                'stop_reason' => StopReason::EXPIRED,
            ]);
            $session = $session->fresh();
        }

        return $session;
    }
}
