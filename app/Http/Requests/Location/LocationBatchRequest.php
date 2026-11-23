<?php

namespace App\Http\Requests\Location;

use Illuminate\Foundation\Http\FormRequest;

class LocationBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'device_uuid' => ['required', 'uuid', 'exists:devices,device_uuid'],
            'locations' => ['required', 'array', 'min:1', 'max:100'],
            'locations.*.event_uuid' => ['required', 'uuid'],
            'locations.*.session_uuid' => ['required', 'uuid'],
            'locations.*.sequence_number' => ['nullable', 'integer', 'min:0'],
            'locations.*.latitude' => ['required', 'numeric', 'between:-90,90'],
            'locations.*.longitude' => ['required', 'numeric', 'between:-180,180'],
            'locations.*.accuracy' => ['nullable', 'numeric', 'min:0'],
            'locations.*.speed' => ['nullable', 'numeric', 'min:0'],
            'locations.*.bearing' => ['nullable', 'numeric', 'between:0,360'],
            'locations.*.altitude' => ['nullable', 'numeric'],
            'locations.*.battery_level' => ['nullable', 'integer', 'between:0,100'],
            'locations.*.recorded_at' => ['required', 'date'],
        ];
    }
}
