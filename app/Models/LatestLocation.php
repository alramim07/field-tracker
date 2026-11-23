<?php

namespace App\Models;

use Database\Factories\LatestLocationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LatestLocation extends Model
{
    /** @use HasFactory<LatestLocationFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'employee_id',
        'session_id',
        'latitude',
        'longitude',
        'accuracy',
        'speed',
        'bearing',
        'battery_level',
        'recorded_at',
        'received_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'accuracy' => 'decimal:2',
            'speed' => 'decimal:2',
            'bearing' => 'decimal:2',
            'battery_level' => 'integer',
            'recorded_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(TrackingSession::class, 'session_id');
    }
}
