<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Services\Auth\AuthService;
use App\Services\Tracking\TrackingSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly TrackingSessionService $trackingSessionService,
    ) {
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login(
            $request->validated('email'),
            $request->validated('password'),
            $request->validated('device_name') ?? 'api-token',
        );

        return response()->json([
            'data' => [
                'token' => $result['token'],
                'user' => new UserResource($result['user']->loadMissing('employee')),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $employee = $user->employee;
        if ($employee) {
            $this->trackingSessionService->endActiveForLogout($employee, $request->ip());
        }

        $this->authService->logout($user);

        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'data' => new UserResource($request->user()->loadMissing('employee')),
        ]);
    }
}
