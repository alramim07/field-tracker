<?php

namespace App\Models;

use App\Enums\TrackingPolicyStatus;
use Database\Factories\TrackingPolicyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrackingPolicy extends Model
{
    /** @use HasFactory<TrackingPolicyFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'start_time',
        'end_time',
        'duration_minutes',
        'moving_interval_seconds',
        'stationary_interval_seconds',
        'status',
        'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => TrackingPolicyStatus::class,
            'duration_minutes' => 'integer',
            'moving_interval_seconds' => 'integer',
            'stationary_interval_seconds' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function trackingSessions(): HasMany
    {
        return $this->hasMany(TrackingSession::class);
    }
}
