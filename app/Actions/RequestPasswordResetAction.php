<?php

namespace App\Actions;

use Illuminate\Support\Facades\Password;

class RequestPasswordResetAction
{
    /**
     * Sends a new definition link only to active accounts, and always returns the
     * same confirmation message so it never reveals whether the email exists (RF-30).
     */
    public function handle(string $email): string
    {
        Password::sendResetLink([
            'email' => mb_strtolower(trim($email)),
            'is_active' => true,
        ]);

        return __('auth.password_reset_requested');
    }
}
