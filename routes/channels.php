<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('admin.live-locations', function (User $user): bool {
    return $user->hasPermission('live_map.view');
});
