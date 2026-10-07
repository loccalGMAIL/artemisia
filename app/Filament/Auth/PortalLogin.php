<?php

namespace App\Filament\Auth;

use App\Actions\AttemptLoginAction;
use App\Enums\AccessMethod;
use App\Enums\AccessPortal;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Validation\ValidationException;

/**
 * Login screen of a portal. It only collects the credentials and shows the
 * outcome: every rule lives in AttemptLoginAction (RF-18 to RF-22, RNF-2).
 */
abstract class PortalLogin extends Login
{
    abstract protected function portal(): AccessPortal;

    public function authenticate(): ?LoginResponse
    {
        $data = $this->form->getState();

        $result = app(AttemptLoginAction::class)->handle(
            ['email' => (string) $data['email'], 'password' => (string) $data['password']],
            $this->portal(),
            AccessMethod::Password,
        );

        if (! $result->successful) {
            throw ValidationException::withMessages(['data.email' => $result->message()]);
        }

        Filament::auth()->login($result->user, (bool) ($data['remember'] ?? false));

        session()->regenerate();

        return app(LoginResponse::class);
    }
}
