<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class AdminUserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $permissions = $this->isSuperAdmin()
            ? ['*']
            : $this->adminRoles->flatMap->permissions->pluck('key')->unique()->values()->all();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role?->value ?? $this->role,
            'status' => $this->status?->value ?? $this->status,
            'designation' => $this->designation,
            'avatar_path' => $this->avatar_path,
            'permissions' => $permissions,
        ];
    }
}
