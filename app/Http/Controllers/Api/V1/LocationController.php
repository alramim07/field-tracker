<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Location\LocationBatchRequest;
use App\Models\Device;
use App\Services\Location\LocationBatchIngestionService;
use Illuminate\Http\JsonResponse;

class LocationController extends Controller
{
    public function __construct(private readonly LocationBatchIngestionService $ingestionService)
    {
    }

    public function batch(LocationBatchRequest $request): JsonResponse
    {
        $user = $request->user();
        $employee = $user->employee;
        if (! $employee) {
            return response()->json(['message' => 'Employee profile not found.'], 403);
        }

        $device = Device::query()->where('device_uuid', $request->validated('device_uuid'))->firstOrFail();
        if ($device->employee_id !== $employee->id) {
            return response()->json(['message' => 'Device does not belong to this employee.'], 403);
        }

        $result = $this->ingestionService->ingest($device, $request->validated('locations'));

        return response()->json(['data' => $result]);
    }
}
