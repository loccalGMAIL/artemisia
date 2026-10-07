<?php

namespace App\Filament\Auth;

use App\Actions\RequestPasswordResetAction;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset;
use Filament\Notifications\Notification;

/**
 * Screen to ask for a new password link. It always answers the same thing, whether
 * or not the email belongs to an active account (RF-30): the rule is in the Action.
 */
class PortalRequestPasswordReset extends RequestPasswordReset
{
    public function request(): void
    {
        $data = $this->form->getState();

        $message = app(RequestPasswordResetAction::class)->handle((string) $data['email']);

        Notification::make()->title($message)->success()->send();

        $this->form->fill();
    }
}
