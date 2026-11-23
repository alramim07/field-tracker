<?php

namespace App\Enums;

enum StopReason: string
{
    case MANUAL = 'manual';
    case EXPIRED = 'expired';
    case REVOKED = 'revoked';
    case SYSTEM = 'system';
}
