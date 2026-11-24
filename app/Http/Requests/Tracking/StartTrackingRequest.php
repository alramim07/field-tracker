<?php

namespace App\Http\Requests\Tracking;

use Illuminate\Foundation\Http\FormRequest;

class StartTrackingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'device_uuid' => ['required', 'uuid', 'exists:devices,device_uuid'],
            'mode' => ['sometimes', 'string', 'in:normal_duty,extra_duty'],
            'client_midnight_at' => ['required_if:mode,extra_duty', 'date'],
            'client_timezone' => ['sometimes', 'string', 'timezone'],
            'client_now' => ['sometimes', 'date'],
        ];
    }
}
