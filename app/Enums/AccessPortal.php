<?php

namespace App\Enums;

use App\Models\User;

enum AccessPortal: string
{
    case Staff = 'staff';
    case Client = 'client';

    /**
     * Whether the account's role corresponds to this portal (RF-21, RF-22).
     */
    public function allows(User $user): bool
    {
        return match ($this) {
            self::Staff => $user->hasAnyRole(['admin', 'staff']),
            self::Client => $user->hasRole('client'),
        };
    }
}
