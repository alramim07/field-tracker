<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\LatestLocation */
class LiveLocationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $employee = $this->employee;
        $user = $employee?->user;

        return [
            'employee_id' => $employee?->id,
            'name' => $user?->name,
            'designation' => $employee?->designation,
            'avatar_path' => $user?->avatar_path,
            'latitude' => (float) $this->latitude,
            'longitude' => (float) $this->longitude,
            'accuracy' => $this->accuracy !== null ? (float) $this->accuracy : null,
            'recorded_at' => optional($this->recorded_at)->toISOString(),
            'status' => 'active',
        ];
    }
}
