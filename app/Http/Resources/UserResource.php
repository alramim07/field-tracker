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
            'total_tracking_seconds' => $this->totalTrackingSeconds($employee),
            'employee' => $this->whenLoaded('employee', fn () => [
                'id' => $this->employee->id,
                'employee_code' => $this->employee->employee_code,
                'region_id' => $this->employee->region_id,
                'area_id' => $this->employee->area_id,
                'designation' => $this->employee->designation,
            ]),
        ];
    }

    private function totalTrackingSeconds(?\App\Models\Employee $employee): int
    {
        if (! $employee) {
            return 0;
        }

        $now = now()->toDateTimeString();
        $driver = $employee->getConnection()->getDriverName();
        $durationExpression = match ($driver) {
            'sqlite' => "COALESCE(SUM(CAST(ROUND((julianday(COALESCE(ended_at, ?)) - julianday(started_at)) * 86400) AS INTEGER)), 0)",
            default => "COALESCE(SUM(TIMESTAMPDIFF(SECOND, started_at, COALESCE(ended_at, ?))), 0)",
        };

        $total = $employee->trackingSessions()
            ->where('counted', true)
            ->selectRaw("{$durationExpression} as total_seconds", [$now])
            ->value('total_seconds');

        return max(0, (int) $total);
    }
}
