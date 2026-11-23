<?php

namespace App\Services\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Employee;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;

class TrackerUserService
{
    public function __construct(private readonly AuditLogService $audit) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor): User
    {
        return DB::transaction(function () use ($data, $actor): User {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => UserRole::EMPLOYEE,
                'status' => UserStatus::ACTIVE,
                'designation' => $data['designation'] ?? null,
            ]);

            Employee::query()->create([
                'user_id' => $user->id,
                'employee_code' => $data['employee_code'],
                'phone' => $data['phone'] ?? null,
                'region_id' => $data['region_id'] ?? null,
                'area_id' => $data['area_id'] ?? null,
                'designation' => $data['designation'] ?? null,
                'status' => $data['status'] ?? 'active',
                'duty_start_time' => $data['duty_start_time'] ?? null,
                'duty_end_time' => $data['duty_end_time'] ?? null,
            ]);

            $this->audit->log('tracker_user.created', $actor, $user, $data);

            return $user->load('employee');
        });
    }

    /** @param array<string, mixed> $data */
    public function update(User $user, array $data, User $actor): User
    {
        return DB::transaction(function () use ($user, $data, $actor): User {
            $userData = array_filter([
                'name' => $data['name'] ?? null,
                'email' => $data['email'] ?? null,
                'password' => $data['password'] ?? null,
                'designation' => $data['designation'] ?? null,
                'status' => $data['status'] ?? null,
            ], fn ($v) => $v !== null);
            if ($userData) {
                $user->update($userData);
            }

            $employee = $user->employee;
            $employeeData = array_filter([
                'employee_code' => $data['employee_code'] ?? null,
                'phone' => $data['phone'] ?? null,
                'region_id' => $data['region_id'] ?? null,
                'area_id' => $data['area_id'] ?? null,
                'designation' => $data['designation'] ?? null,
                'status' => $data['status'] ?? null,
                'duty_start_time' => $data['duty_start_time'] ?? null,
                'duty_end_time' => $data['duty_end_time'] ?? null,
            ], fn ($v) => $v !== null);
            if ($employee && $employeeData) {
                $employee->update($employeeData);
            }

            $this->audit->log('tracker_user.updated', $actor, $user, $data);

            return $user->load('employee');
        });
    }

    public function delete(User $user, User $actor): void
    {
        $this->audit->log('tracker_user.deleted', $actor, $user, []);
        $user->delete();
    }
}
