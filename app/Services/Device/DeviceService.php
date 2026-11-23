<?php

namespace App\Services\Device;

use App\Enums\DeviceStatus;
use App\Models\Device;
use App\Models\Employee;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeviceService
{
    public function __construct(private readonly AuditLogService $auditLogService)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function register(Employee $employee, array $data, ?string $ipAddress = null): Device
    {
        return DB::transaction(function () use ($employee, $data, $ipAddress): Device {
            $existing = Device::query()->where('device_uuid', $data['device_uuid'])->first();

            if ($existing && $existing->employee_id !== $employee->id) {
                throw ValidationException::withMessages([
                    'device_uuid' => ['Device is registered to another employee. Contact administrator.'],
                ]);
            }

            $device = Device::query()->updateOrCreate(
                ['device_uuid' => $data['device_uuid']],
                [
                    'employee_id' => $employee->id,
                    'manufacturer' => $data['manufacturer'] ?? null,
                    'model' => $data['model'] ?? null,
                    'android_version' => $data['android_version'] ?? null,
                    'app_version' => $data['app_version'] ?? null,
                    'status' => DeviceStatus::ACTIVE,
                    'last_seen_at' => now(),
                    'registered_at' => $existing?->registered_at ?? now(),
                ]
            );

            $this->auditLogService->log(
                $existing ? 'device.updated' : 'device.registered',
                $employee->user,
                $device,
                ['employee_id' => $employee->id],
                $ipAddress,
            );

            return $device;
        });
    }

    public function revoke(Device $device, ?\App\Models\User $actor = null, ?string $ipAddress = null): Device
    {
        $device->update(['status' => DeviceStatus::REVOKED]);

        $this->auditLogService->log('device.revoked', $actor, $device, [], $ipAddress);

        return $device->fresh();
    }
}
