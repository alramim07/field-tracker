<?php

namespace App\Enums;

enum TrackingSessionStatus: string
{
    case ACTIVE = 'active';
    case STOPPED = 'stopped';
    case EXPIRED = 'expired';
}
