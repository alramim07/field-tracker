<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAdminUserRequest;
use App\Http\Requests\Admin\UpdateAdminPermissionsRequest;
use App\Http\Resources\Admin\AdminUserResource;
use App\Models\User;
use App\Services\Admin\AdminUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    public function __construct(private readonly AdminUserService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $users = User::query()
            ->whereIn('role', [\App\Enums\UserRole::ADMIN, \App\Enums\UserRole::SUPER_ADMIN])
            ->with('adminRoles.permissions')
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return AdminUserResource::collection($users)->response();
    }

    public function store(StoreAdminUserRequest $request): JsonResponse
    {
        $user = $this->service->create($request->validated(), $request->user());

        return (new AdminUserResource($user))->response()->setStatusCode(201);
    }

    public function show(User $admin_user): JsonResponse
    {
        return (new AdminUserResource($admin_user->load('adminRoles.permissions')))->response();
    }

    public function updatePermissions(UpdateAdminPermissionsRequest $request, User $admin_user): JsonResponse
    {
        $user = $this->service->syncPermissions(
            $admin_user,
            $request->validated('permissions'),
            $request->user(),
            $request->validated('designation'),
        );

        return (new AdminUserResource($user))->response();
    }
}
