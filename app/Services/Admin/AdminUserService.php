<?php

namespace App\Services\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AdminRole;
use App\Models\Permission;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminUserService
{
    public function __construct(private readonly AuditLogService $audit)
    {
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor): User
    {
        return DB::transaction(function () use ($data, $actor): User {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => UserRole::ADMIN,
                'status' => UserStatus::ACTIVE,
                'designation' => $data['designation'] ?? null,
            ]);

            $this->syncPermissions($user, $data['permissions'] ?? []);
            $this->audit->log('admin_user.created', $actor, $user, $data);

            return $user->load('adminRoles.permissions');
        });
    }

    /** @param array<int, string> $permissionKeys */
    public function syncPermissions(User $user, array $permissionKeys, ?User $actor = null, ?string $designation = null): User
    {
        return DB::transaction(function () use ($user, $permissionKeys, $actor, $designation): User {
            if ($designation !== null) {
                $user->update(['designation' => $designation]);
            }

            $slug = 'admin-user-'.$user->id;
            $role = AdminRole::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => $user->name.' Role', 'is_system' => false]
            );
            $permissionIds = Permission::query()->whereIn('key', $permissionKeys)->pluck('id')->all();
            $role->permissions()->sync($permissionIds);
            $user->adminRoles()->syncWithoutDetaching([$role->id]);

            if ($actor) {
                $this->audit->log('admin_user.permissions_synced', $actor, $user, ['permissions' => $permissionKeys]);
            }

            return $user->load('adminRoles.permissions');
        });
    }
}
