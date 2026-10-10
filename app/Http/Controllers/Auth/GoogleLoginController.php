<?php

namespace App\Http\Controllers\Auth;

use App\Actions\AttemptLoginAction;
use App\Enums\AccessMethod;
use App\Enums\AccessPortal;
use App\Http\Controllers\Controller;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Throwable;

/**
 * Google sign-in for one portal (D-10). It only moves the user between Google
 * and the portal: the decision to admit the account is AttemptLoginAction's.
 */
class GoogleLoginController extends Controller
{
    public function redirect(AccessPortal $portal): RedirectResponse
    {
        return $this->provider($portal)->redirect();
    }

    public function callback(Request $request, AccessPortal $portal, AttemptLoginAction $attemptLogin): RedirectResponse
    {
        $panel = Filament::getPanel($portal->value);

        if ($request->has('error')) {
            return $this->rejected($panel, __('auth.google_failed'));
        }

        try {
            $googleUser = $this->provider($portal)->user();
        } catch (Throwable $exception) {
            report($exception);

            return $this->rejected($panel, __('auth.google_failed'));
        }

        $result = $attemptLogin->handle($googleUser, $portal, AccessMethod::Google);

        if (! $result->successful) {
            return $this->rejected($panel, $result->message());
        }

        $panel->auth()->login($result->user);

        $request->session()->regenerate();

        return redirect($panel->getUrl());
    }

    private function provider(AccessPortal $portal): mixed
    {
        /** @var AbstractProvider $driver */
        $driver = Socialite::driver('google');

        return $driver->redirectUrl(route("google.{$portal->value}.callback"));
    }

    private function rejected(Panel $panel, string $message): RedirectResponse
    {
        return redirect($panel->getLoginUrl())->with('login_error', $message);
    }
}
