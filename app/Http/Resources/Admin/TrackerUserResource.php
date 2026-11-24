<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class TrackerUserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $employee = $this->employee;

        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role?->value ?? $this->role,
            'status' => $this->status?->value ?? $this->status,
            'designation' => $this->designation,
            'employee' => $employee ? [
                'id' => $employee->id,
                'employee_code' => $employee->employee_code,
                'phone' => $employee->phone,
                'region_id' => $employee->region_id,
                'area_id' => $employee->area_id,
                'designation' => $employee->designation,
                'status' => $employee->status,
                'duty_start_time' => $employee->duty_start_time,
                'duty_end_time' => $employee->duty_end_time,
            ] : null,
        ];

        if (array_key_exists('extra_duty_audit', $this->resource->getAttributes())) {
            $data['extra_duty_audit'] = $this->resource->getAttribute('extra_duty_audit');
        }

        return $data;
    }
}
