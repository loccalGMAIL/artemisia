<?php

namespace App\Providers\Filament;

use App\Filament\Auth\PortalRequestPasswordReset;
use App\Filament\Auth\PortalResetPassword;
use App\Filament\Client\Pages\Auth\Login;
use App\Filament\Client\Pages\ClientProfilePage;
use App\Http\Middleware\EnsureAccountIsActive;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class ClientPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('client')
            ->path('portal')
            ->login(Login::class)
            ->passwordReset(PortalRequestPasswordReset::class, PortalResetPassword::class)
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                fn (): View => view('auth.google-login', ['url' => route('google.client.redirect')]),
            )
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Client/Resources'), for: 'App\Filament\Client\Resources')
            ->discoverPages(in: app_path('Filament/Client/Pages'), for: 'App\Filament\Client\Pages')
            ->pages([
                ClientProfilePage::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Client/Widgets'), for: 'App\Filament\Client\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                EnsureAccountIsActive::class,
            ]);
    }
}
