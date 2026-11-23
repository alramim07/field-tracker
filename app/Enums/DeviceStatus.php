<?php

namespace App\Enums;

enum DeviceStatus: string
{
    case ACTIVE = 'active';
    case REVOKED = 'revoked';
    case INACTIVE = 'inactive';
}
