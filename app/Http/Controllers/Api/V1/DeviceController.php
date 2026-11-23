<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Device\RegisterDeviceRequest;
use App\Models\Device;
use App\Services\Device\DeviceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    public function __construct(private readonly DeviceService $deviceService)
    {
    }

    public function register(RegisterDeviceRequest $request): JsonResponse
    {
        $user = $request->user();
        $employee = $user->employee;

        if (! $employee) {
            return response()->json(['message' => 'Employee profile not found.'], 403);
        }

        $device = $this->deviceService->register(
            $employee,
            $request->validated(),
            $request->ip(),
        );

        return response()->json([
            'data' => [
                'id' => $device->id,
                'device_uuid' => $device->device_uuid,
                'status' => $device->status->value,
                'employee_id' => $device->employee_id,
                'registered_at' => $device->registered_at,
                'last_seen_at' => $device->last_seen_at,
            ],
        ], 201);
    }

    public function revoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_uuid' => ['required', 'uuid', 'exists:devices,device_uuid'],
        ]);

        $this->authorizeAdmin($request);

        $device = Device::query()->where('device_uuid', $validated['device_uuid'])->firstOrFail();
        $device = $this->deviceService->revoke($device, $request->user(), $request->ip());

        return response()->json([
            'data' => [
                'device_uuid' => $device->device_uuid,
                'status' => $device->status->value,
            ],
        ]);
    }

    private function authorizeAdmin(Request $request): void
    {
        $user = $request->user();
        if (! $user || ! $user->isAdmin()) {
            abort(403, 'Forbidden.');
        }
    }
}
