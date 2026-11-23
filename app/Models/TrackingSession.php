<?php

namespace App\Models;

use App\Enums\StopReason;
use App\Enums\TrackingSessionStatus;
use Database\Factories\TrackingSessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TrackingSession extends Model
{
    /** @use HasFactory<TrackingSessionFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'session_uuid',
        'employee_id',
        'device_id',
        'tracking_policy_id',
        'policy_snapshot',
        'started_at',
        'expires_at',
        'ended_at',
        'status',
        'stop_reason',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'policy_snapshot' => 'array',
            'started_at' => 'datetime',
            'expires_at' => 'datetime',
            'ended_at' => 'datetime',
            'status' => TrackingSessionStatus::class,
            'stop_reason' => StopReason::class,
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function trackingPolicy(): BelongsTo
    {
        return $this->belongsTo(TrackingPolicy::class);
    }

    public function locationLogs(): HasMany
    {
        return $this->hasMany(LocationLog::class, 'session_id');
    }

    public function latestLocation(): HasOne
    {
        return $this->hasOne(LatestLocation::class, 'session_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
