<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\LiveLocationResource;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\Employee;
use App\Models\LatestLocation;
use App\Models\TrackingPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function employees(Request $request): JsonResponse
    {
        $query = Employee::query()->with(['user:id,name,email,role,status', 'region:id,name,code', 'area:id,name,code']);
        if ($request->filled('region_id')) $query->where('region_id', $request->integer('region_id'));
        if ($request->filled('area_id')) $query->where('area_id', $request->integer('area_id'));
        return response()->json($query->paginate(min((int) $request->integer('per_page', 20), 100)));
    }

    public function devices(Request $request): JsonResponse
    {
        return response()->json(Device::query()->with('employee:id,employee_code,user_id')->latest()->paginate(min((int) $request->integer('per_page', 20), 100)));
    }

    public function trackingPolicies(Request $request): JsonResponse
    {
        return response()->json(TrackingPolicy::query()->latest()->paginate(min((int) $request->integer('per_page', 20), 100)));
    }

    public function auditLogs(Request $request): JsonResponse
    {
        return response()->json(AuditLog::query()->with('user:id,name,email')->latest()->paginate(min((int) $request->integer('per_page', 20), 100)));
    }

    public function liveLocations(Request $request): JsonResponse
    {
        $query = LatestLocation::query()->with(['employee.user:id,name,avatar_path', 'employee:id,user_id,designation,employee_code']);
        $locations = $query->latest('recorded_at')->paginate(min((int) $request->integer('per_page', 50), 200));

        return LiveLocationResource::collection($locations)->response();
    }
}
