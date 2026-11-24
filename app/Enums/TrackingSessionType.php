<?php

namespace App\Enums;

enum TrackingSessionType: string
{
    case NORMAL_DUTY = 'normal_duty';
    case EXTRA_DUTY = 'extra_duty';
}
