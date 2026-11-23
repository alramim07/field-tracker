<?php

namespace App\Events;

use App\Models\LatestLocation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LatestLocationUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly LatestLocation $latestLocation)
    {
    }
}
