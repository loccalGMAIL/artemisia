<?php

namespace App\Services;

use App\Enums\AccessMethod;
use App\Enums\AccessOutcome;
use App\Enums\AccessPortal;
use App\Enums\AccessRejection;
use App\Models\AccessLog;
use App\Models\User;

class AccessLogger
{
    public function log(
        ?User $user,
        string $emailUsed,
        AccessPortal $portal,
        AccessMethod $method,
        AccessOutcome $outcome,
        ?AccessRejection $reason = null,
    ): void {
        AccessLog::query()->create([
            'user_id' => $user?->id,
            'email_used' => $emailUsed,
            'portal' => $portal,
            'method' => $method,
            'outcome' => $outcome,
            'rejection_reason' => $reason?->value,
        ]);
    }
}
