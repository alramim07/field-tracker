<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\ChangePasswordRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Requests\Profile\UploadPhotoRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        if (array_key_exists('email', $validated)) {
            $user->email = $validated['email'];
            $user->save();
        }

        $employee = $user->employee;
        if ($employee && array_key_exists('phone', $validated)) {
            $employee->phone = $validated['phone'];
            $employee->save();
        }

        return response()->json([
            'data' => new UserResource($user->fresh()->loadMissing('employee')),
        ]);
    }

    public function uploadPhoto(UploadPhotoRequest $request): JsonResponse
    {
        $user = $request->user();
        $path = $request->file('photo')->store('avatars', 'public');

        $oldPath = $user->avatar_path;
        $user->avatar_path = $path;
        $user->save();

        if ($oldPath && $oldPath !== $path) {
            Storage::disk('public')->delete($oldPath);
        }

        return response()->json([
            'data' => [
                'photo_url' => Storage::disk('public')->url($path),
            ],
        ]);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->password = $request->validated('password');
        $user->save();

        $currentTokenId = $user->currentAccessToken()?->id;
        $query = $user->tokens();
        if ($currentTokenId !== null) {
            $query->where('id', '!=', $currentTokenId);
        }
        $query->delete();

        return response()->json([
            'message' => 'Password changed successfully.',
        ]);
    }
}
