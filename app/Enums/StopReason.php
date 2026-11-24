<?php

namespace App\Enums;

enum StopReason: string
{
    case MANUAL = 'manual';
    case EXPIRED = 'expired';
    case REVOKED = 'revoked';
    case SYSTEM = 'system';
    case EXTENDED_MANUAL = 'extended_manual';
    case MIDNIGHT_AUTO = 'midnight_auto';
    case LOGOUT = 'logout';
}
