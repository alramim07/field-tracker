<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminLoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AdminAuthController extends Controller
{
    public function login(AdminLoginRequest $request): JsonResponse
    {
        if (! Auth::guard('web')->attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages(['email' => ['Invalid credentials.']]);
        }

        $request->session()->regenerate();

        /** @var User $user */
        $user = Auth::guard('web')->user();
        if (! $user->isAdmin() || $user->status !== UserStatus::ACTIVE) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            abort(403, 'Forbidden.');
        }

        return response()->json(['data' => $this->payload($user)]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user('web') ?? $request->user();

        return response()->json(['data' => $this->payload($user)]);
    }

    /** @return array<string, mixed> */
    private function payload(User $user): array
    {
        $user->loadMissing('employee', 'adminRoles.permissions');

        $permissions = $user->isSuperAdmin()
            ? ['*']
            : $user->adminRoles->flatMap->permissions->pluck('key')->unique()->values()->all();

        return [
            'user' => new UserResource($user),
            'permissions' => $permissions,
        ];
    }
}
