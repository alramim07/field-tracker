<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTrackerUserRequest;
use App\Http\Requests\Admin\UpdateTrackerUserRequest;
use App\Http\Resources\Admin\TrackerUserResource;
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
        return (new TrackerUserResource($tracker_user->load('employee')))->response();
    }

    public function update(UpdateTrackerUserRequest $request, User $tracker_user): JsonResponse
    {
        $user = $this->service->update($tracker_user, $request->validated(), $request->user());

        return (new TrackerUserResource($user))->response();
    }

    public function destroy(Request $request, User $tracker_user): JsonResponse
    {
        $this->service->delete($tracker_user, $request->user());

        return response()->json(['message' => 'Deleted.']);
    }
}
