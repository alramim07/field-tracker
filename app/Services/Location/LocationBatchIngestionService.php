<?php

namespace App\Services\Location;

use App\Enums\TrackingSessionStatus;
use App\Events\LatestLocationUpdated;
use App\Models\Device;
use App\Models\LatestLocation;
use App\Models\LocationLog;
use App\Models\TrackingSession;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LocationBatchIngestionService
{
    /**
     * @param array<int, array<string, mixed>> $locations
     * @return array{accepted: int, duplicates: int, rejected: int, errors: array<int, array<string, mixed>>}
     */
    public function ingest(Device $device, array $locations): array
    {
        $accepted = 0;
        $duplicates = 0;
        $rejected = 0;
        $errors = [];

        foreach ($locations as $index => $location) {
            try {
                $result = $this->ingestOne($device, $location);
                if ($result === 'accepted') {
                    $accepted++;
                } elseif ($result === 'duplicate') {
                    $duplicates++;
                }
            } catch (ValidationException $e) {
                $rejected++;
                $errors[] = ['index' => $index, 'event_uuid' => $location['event_uuid'] ?? null, 'errors' => $e->errors()];
            }
        }

        return compact('accepted', 'duplicates', 'rejected', 'errors');
    }

    /**
     * @param array<string, mixed> $location
     */
    private function ingestOne(Device $device, array $location): string
    {
        return DB::transaction(function () use ($device, $location): string {
            $session = TrackingSession::query()
                ->where('session_uuid', $location['session_uuid'])
                ->lockForUpdate()
                ->first();

            if (! $session) {
                throw ValidationException::withMessages(['session_uuid' => ['Session not found.']]);
            }

            if ($session->device_id !== $device->id || $session->employee_id !== $device->employee_id) {
                throw ValidationException::withMessages(['session_uuid' => ['Session does not belong to this device.']]);
            }

            if ($session->status !== TrackingSessionStatus::ACTIVE) {
                throw ValidationException::withMessages(['session_uuid' => ['Session is not active.']]);
            }

            $recordedAt = Carbon::parse($location['recorded_at']);

            if ($recordedAt->greaterThan($session->expires_at)) {
                throw ValidationException::withMessages(['recorded_at' => ['Location recorded after session expiry.']]);
            }

            $exists = LocationLog::query()->where('event_uuid', $location['event_uuid'])->exists();
            if ($exists) {
                return 'duplicate';
            }

            $receivedAt = now();

            LocationLog::query()->create([
                'event_uuid' => $location['event_uuid'],
                'employee_id' => $session->employee_id,
                'device_id' => $device->id,
                'session_id' => $session->id,
                'latitude' => $location['latitude'],
                'longitude' => $location['longitude'],
                'accuracy' => $location['accuracy'] ?? null,
                'speed' => $location['speed'] ?? null,
                'bearing' => $location['bearing'] ?? null,
                'altitude' => $location['altitude'] ?? null,
                'battery_level' => $location['battery_level'] ?? null,
                'recorded_at' => $recordedAt,
                'received_at' => $receivedAt,
            ]);

            $this->upsertLatestLocation($session, $location, $recordedAt, $receivedAt);

            $device->forceFill(['last_seen_at' => $receivedAt])->save();

            return 'accepted';
        });
    }

    /**
     * @param array<string, mixed> $location
     */
    private function upsertLatestLocation(TrackingSession $session, array $location, Carbon $recordedAt, Carbon $receivedAt): void
    {
        $latest = LatestLocation::query()
            ->where('employee_id', $session->employee_id)
            ->lockForUpdate()
            ->first();

        $payload = [
            'session_id' => $session->id,
            'latitude' => $location['latitude'],
            'longitude' => $location['longitude'],
            'accuracy' => $location['accuracy'] ?? null,
            'speed' => $location['speed'] ?? null,
            'bearing' => $location['bearing'] ?? null,
            'battery_level' => $location['battery_level'] ?? null,
            'recorded_at' => $recordedAt,
            'received_at' => $receivedAt,
        ];

        if (! $latest) {
            $latest = LatestLocation::query()->create(['employee_id' => $session->employee_id] + $payload);
            event(new LatestLocationUpdated($latest));
            return;
        }

        if ($recordedAt->greaterThan($latest->recorded_at)) {
            $latest->fill($payload)->save();
            event(new LatestLocationUpdated($latest));
        }
    }
}
