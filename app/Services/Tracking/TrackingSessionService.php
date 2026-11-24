<?php

namespace App\Services\Tracking;

use App\Enums\DeviceStatus;
use App\Enums\StopReason;
use App\Enums\TrackingPolicyStatus;
use App\Enums\TrackingSessionStatus;
use App\Enums\TrackingSessionType;
use App\Models\Device;
use App\Models\Employee;
use App\Models\TrackingPolicy;
use App\Models\TrackingSession;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TrackingSessionService
{
    public function __construct(private readonly AuditLogService $auditLogService)
    {
    }

    public function start(
        Employee $employee,
        Device $device,
        ?string $ipAddress = null,
        string $mode = 'normal_duty',
        ?string $clientMidnightAt = null,
        ?string $clientTimezone = null,
        ?string $clientNow = null,
    ): TrackingSession {
        if ($device->employee_id !== $employee->id) {
            throw ValidationException::withMessages(['device_uuid' => ['Device does not belong to this employee.']]);
        }

        if ($device->status !== DeviceStatus::ACTIVE) {
            throw ValidationException::withMessages(['device_uuid' => ['Device is not active.']]);
        }

        if (! in_array($mode, ['normal_duty', 'extra_duty'], true)) {
            throw ValidationException::withMessages(['mode' => ['Invalid tracking mode.']]);
        }

        $policy = TrackingPolicy::query()
            ->where('status', TrackingPolicyStatus::ACTIVE)
            ->latest('id')
            ->first();

        if (! $policy) {
            throw ValidationException::withMessages(['policy' => ['No active tracking policy configured.']]);
        }

        return DB::transaction(function () use ($employee, $device, $policy, $ipAddress, $mode, $clientMidnightAt): TrackingSession {
            $active = TrackingSession::query()
                ->where('employee_id', $employee->id)
                ->where('status', TrackingSessionStatus::ACTIVE)
                ->lockForUpdate()
                ->first();

            if ($active) {
                if ($active->expires_at->isPast()) {
                    $active->markAsExpired();
                } else {
                    throw ValidationException::withMessages(['session' => ['An active tracking session already exists.']]);
                }
            }

            $startedAt = now();

            if ($mode === 'extra_duty') {
                return $this->startExtraDuty($employee, $device, $policy, $ipAddress, $startedAt, $clientMidnightAt);
            }

            return $this->startNormalDuty($employee, $device, $policy, $ipAddress, $startedAt);
        });
    }

    private function startNormalDuty(Employee $employee, Device $device, TrackingPolicy $policy, ?string $ipAddress, Carbon $startedAt): TrackingSession
    {
        if (! $employee->duty_end_time) {
            throw ValidationException::withMessages(['duty_end_time' => ['Employee duty end time is required.']]);
        }

        $tz = 'Asia/Dhaka';
        $nowLocal = $startedAt->copy()->setTimezone($tz);
        $expiresAt = Carbon::parse($nowLocal->toDateString().' '.$employee->duty_end_time, $tz);

        if ($expiresAt->lte($nowLocal)) {
            throw ValidationException::withMessages(['session' => ['Normal duty has already ended for today.']]);
        }

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
            'expires_at' => $expiresAt->copy()->setTimezone(config('app.timezone')),
            'status' => TrackingSessionStatus::ACTIVE,
            'session_type' => TrackingSessionType::NORMAL_DUTY,
            'counted' => true,
        ]);

        $this->auditLogService->log('tracking.session_started', $employee->user, $session, [
            'device_id' => $device->id,
            'policy_id' => $policy->id,
            'session_type' => TrackingSessionType::NORMAL_DUTY->value,
        ], $ipAddress);

        return $session;
    }

    private function startExtraDuty(Employee $employee, Device $device, TrackingPolicy $policy, ?string $ipAddress, Carbon $startedAt, ?string $clientMidnightAt): TrackingSession
    {
        if (! $clientMidnightAt) {
            throw ValidationException::withMessages(['client_midnight_at' => ['client_midnight_at is required for extra duty.']]);
        }

        $expiresAt = Carbon::parse($clientMidnightAt);
        $dayEnd = $expiresAt->copy()->setTimezone(config('app.timezone'));
        $dayStart = $dayEnd->copy()->subDay();

        $tz = 'Asia/Dhaka';
        $nowLocal = $startedAt->copy()->setTimezone($tz);
        if ($employee->duty_end_time) {
            $dutyEnd = Carbon::parse($nowLocal->toDateString().' '.$employee->duty_end_time, $tz);
            if ($nowLocal->lt($dutyEnd)) {
                throw ValidationException::withMessages(['session' => ['Extra duty can only start after normal duty ends.']]);
            }
        }

        $normalEndedToday = TrackingSession::query()
            ->where('employee_id', $employee->id)
            ->where('session_type', TrackingSessionType::NORMAL_DUTY)
            ->whereIn('status', [TrackingSessionStatus::STOPPED, TrackingSessionStatus::EXPIRED])
            ->whereNotNull('ended_at')
            ->where('ended_at', '>=', $dayStart)
            ->where('ended_at', '<', $dayEnd)
            ->exists();

        if (! $normalEndedToday) {
            throw ValidationException::withMessages(['session' => ['A normal duty session must end before starting extra duty.']]);
        }

        $extraExists = TrackingSession::query()
            ->where('employee_id', $employee->id)
            ->where('session_type', TrackingSessionType::EXTRA_DUTY)
            ->where('started_at', '>=', $dayStart)
            ->where('started_at', '<', $dayEnd)
            ->exists();

        if ($extraExists) {
            throw ValidationException::withMessages(['session' => ['Extra duty has already been used for this day.']]);
        }

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
            'expires_at' => $dayEnd->copy()->setTimezone(config('app.timezone')),
            'status' => TrackingSessionStatus::ACTIVE,
            'session_type' => TrackingSessionType::EXTRA_DUTY,
            'counted' => true,
        ]);

        $this->auditLogService->log('tracking.session_started', $employee->user, $session, [
            'device_id' => $device->id,
            'policy_id' => $policy->id,
            'session_type' => TrackingSessionType::EXTRA_DUTY->value,
        ], $ipAddress);

        return $session;
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
            $session->markAsExpired();
            throw ValidationException::withMessages(['session' => ['Tracking session has already expired.']]);
        }

        if ($session->session_type !== TrackingSessionType::EXTRA_DUTY) {
            throw ValidationException::withMessages(['session' => ['Normal duty sessions cannot be stopped manually.']]);
        }

        $session->update([
            'status' => TrackingSessionStatus::STOPPED,
            'ended_at' => now(),
            'stop_reason' => StopReason::EXTENDED_MANUAL,
        ]);

        $this->auditLogService->log('tracking.session_stopped', $employee->user, $session, [
            'stop_reason' => StopReason::EXTENDED_MANUAL->value,
        ], $ipAddress);

        return $session->fresh();
    }

    public function status(Employee $employee): ?TrackingSession
    {
        $session = TrackingSession::query()
            ->where('employee_id', $employee->id)
            ->latest('started_at')
            ->first();

        if ($session && $session->status === TrackingSessionStatus::ACTIVE && $session->expires_at->isPast()) {
            $session->markAsExpired();
            $session = $session->fresh();
        }

        return $session;
    }

    public function statusMeta(Employee $employee, ?TrackingSession $session = null, ?string $clientMidnightAt = null): array
    {
        $nowLocal = now()->setTimezone('Asia/Dhaka');

        if ($clientMidnightAt) {
            try {
                $dayEnd = Carbon::parse($clientMidnightAt)->setTimezone(config('app.timezone'));
                $dayStart = $dayEnd->copy()->subDay();
            } catch (\Throwable) {
                $dayStart = $nowLocal->copy()->startOfDay();
                $dayEnd = $dayStart->copy()->addDay();
            }
        } else {
            $dayStart = $nowLocal->copy()->startOfDay();
            $dayEnd = $dayStart->copy()->addDay();
        }

        $hasUsedExtraToday = TrackingSession::query()
            ->where('employee_id', $employee->id)
            ->where('session_type', TrackingSessionType::EXTRA_DUTY)
            ->where('started_at', '>=', $dayStart)
            ->where('started_at', '<', $dayEnd)
            ->exists();

        $active = $session && $session->status === TrackingSessionStatus::ACTIVE ? $session : null;
        $canExtend = false;

        if (! $active && ! $hasUsedExtraToday && $employee->duty_end_time) {
            $dutyEnd = Carbon::parse($nowLocal->toDateString().' '.$employee->duty_end_time, 'Asia/Dhaka');
            $normalEndedToday = TrackingSession::query()
                ->where('employee_id', $employee->id)
                ->where('session_type', TrackingSessionType::NORMAL_DUTY)
                ->whereIn('status', [TrackingSessionStatus::STOPPED, TrackingSessionStatus::EXPIRED])
                ->whereNotNull('ended_at')
                ->where('ended_at', '>=', $dayStart)
                ->where('ended_at', '<', $dayEnd)
                ->exists();

            $canExtend = $nowLocal->gte($dutyEnd) && $normalEndedToday;
        }

        return [
            'can_extend' => $canExtend,
            'has_used_extra_today' => $hasUsedExtraToday,
        ];
    }

    public function endActiveForLogout(Employee $employee, ?string $ipAddress = null): void
    {
        $session = TrackingSession::query()
            ->where('employee_id', $employee->id)
            ->where('status', TrackingSessionStatus::ACTIVE)
            ->latest('started_at')
            ->first();

        if (! $session) {
            return;
        }

        if ($session->expires_at->isPast()) {
            $session->markAsExpired();
            return;
        }

        $session->update([
            'status' => TrackingSessionStatus::STOPPED,
            'ended_at' => now(),
            'stop_reason' => StopReason::LOGOUT,
        ]);

        $this->auditLogService->log('tracking.session_stopped', $employee->user, $session, [
            'stop_reason' => StopReason::LOGOUT->value,
        ], $ipAddress);
    }
}
