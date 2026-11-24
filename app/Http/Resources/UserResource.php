<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin \App\Models\User */
class UserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $employee = $this->employee;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role?->value ?? $this->role,
            'status' => $this->status?->value ?? $this->status,
            'phone' => $employee?->phone,
            'photo_url' => $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null,
            'duty_start_time' => $employee?->duty_start_time,
            'duty_end_time' => $employee?->duty_end_time,
            'total_tracking_seconds' => $employee
                ? (int) $employee->trackingSessions()
                    ->where('counted', true)
                    ->get(['started_at', 'ended_at'])
                    ->sum(fn ($session) => $session->started_at?->diffInSeconds($session->ended_at ?? now()) ?? 0)
                : 0,
            'employee' => $this->whenLoaded('employee', fn () => [
                'id' => $this->employee->id,
                'employee_code' => $this->employee->employee_code,
                'region_id' => $this->employee->region_id,
                'area_id' => $this->employee->area_id,
                'designation' => $this->employee->designation,
            ]),
        ];
    }
}
