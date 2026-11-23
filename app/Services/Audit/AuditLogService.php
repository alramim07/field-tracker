<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditLogService
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function log(
        string $action,
        ?User $user = null,
        ?Model $target = null,
        array $metadata = [],
        ?string $ipAddress = null,
    ): AuditLog {
        return AuditLog::query()->create([
            'user_id' => $user?->id,
            'action' => $action,
            'target_type' => $target ? $target->getMorphClass() : null,
            'target_id' => $target?->getKey(),
            'metadata' => $metadata ?: null,
            'ip_address' => $ipAddress,
        ]);
    }
}
