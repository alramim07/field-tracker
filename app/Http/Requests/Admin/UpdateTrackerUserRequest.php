<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTrackerUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $userId = $this->route('tracker_user');
        $employeeId = null;
        if ($userId) {
            $employeeId = \App\Models\User::query()->whereKey($userId)->value('id');
            $employeeId = \App\Models\Employee::query()->where('user_id', $userId)->value('id');
        }

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'password' => ['nullable', 'string', 'min:8'],
            'employee_code' => ['sometimes', 'required', 'string', 'max:100', Rule::unique('employees', 'employee_code')->ignore($employeeId)],
            'phone' => ['nullable', 'string', 'max:50'],
            'region_id' => ['nullable', 'integer', 'exists:regions,id'],
            'area_id' => ['nullable', 'integer', 'exists:areas,id'],
            'designation' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
            'duty_start_time' => ['required', 'date_format:H:i,H:i:s'],
            'duty_end_time' => ['required', 'date_format:H:i,H:i:s', 'after:duty_start_time'],
        ];
    }
}
