<?php

namespace App\Services\Rbac;

use App\Models\User;

class PermissionService
{
    public function userHasPermission(User $user, string $key): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->adminRoles()
            ->whereHas('permissions', fn ($query) => $query->where('key', $key))
            ->exists();
    }
}
