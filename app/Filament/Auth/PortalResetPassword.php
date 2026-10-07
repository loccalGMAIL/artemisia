<?php

namespace App\Filament\Auth;

use App\Actions\SetPasswordAction;
use App\Exceptions\InvalidPasswordLinkException;
use Filament\Actions\Action;
use Filament\Auth\Http\Responses\Contracts\PasswordResetResponse;
use Filament\Auth\Pages\PasswordReset\ResetPassword;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;

/**
 * Screen to define a password from a link (RF-27, RF-28). The token, expiry and
 * password rules are SetPasswordAction's; this page only collects and shows.
 */
class PortalResetPassword extends ResetPassword
{
    public function resetPassword(): ?PasswordResetResponse
    {
        $data = $this->form->getState();

        try {
            app(SetPasswordAction::class)->handle((string) $this->token, (string) $this->email, (string) $data['password']);
        } catch (InvalidPasswordLinkException $exception) {
            Notification::make()
                ->title($exception->getMessage())
                ->danger()
                ->actions([
                    Action::make('requestNewLink')
                        ->label(__('auth.request_new_link'))
                        ->url(Filament::getRequestPasswordResetUrl())
                        ->button(),
                ])
                ->send();

            return null;
        }

        Notification::make()->title(__('auth.password_defined'))->success()->send();

        return app(PasswordResetResponse::class);
    }
}
