<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreTrackerUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'employee_code' => ['required', 'string', 'max:100', 'unique:employees,employee_code'],
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
