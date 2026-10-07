<?php

namespace App\Enums;

use App\Models\User;

enum AccessPortal: string
{
    case Staff = 'staff';
    case Client = 'client';

    /**
     * The portal an account belongs to by role: clients to theirs, everyone else to staff.
     */
    public static function forAccount(User $user): self
    {
        return $user->hasRole('client') ? self::Client : self::Staff;
    }

    public function label(): string
    {
        return __('logs.portals.'.$this->value);
    }

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
