<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Panel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

class GoogleLoginController extends Controller
{
    /**
     * Registra las rutas de ingreso con Google dentro del grupo de rutas de
     * un panel de Filament (ver `Panel::routes()` en AdminPanelProvider y
     * ClientePanelProvider). Al registrarse ahí, heredan gratis el dominio
     * del panel (admin.* / clientes.*) y su stack de middleware de sesión
     * (EncryptCookies, StartSession, AuthenticateSession...) — el mismo que
     * usa el login normal, así que la cookie de sesión y el `state` del
     * OAuth quedan atados al subdominio correcto sin configurar nada aparte.
     *
     * Nota sobre pruebas en local: Google solo acepta el host literal
     * "localhost" (no "admin.localhost") como redirect_uri con http. Se
     * evaluó servir un callback único ahí para probar en desarrollo, pero
     * Chrome no comparte cookies de sesión entre "localhost" y sus
     * "subdominios" (ni siquiera forzando `SESSION_DOMAIN=.localhost`), así
     * que el login se autentica en la sesión de "localhost" y nunca llega
     * a la de "admin.localhost"/"clientes.localhost" — un callejón sin
     * salida. Probar el flujo real requiere HTTPS con un dominio propio
     * (mkcert + proxy) o el despliegue a producción; mientras tanto la
     * lógica queda cubierta por `tests/Feature/GoogleLoginTest.php` con
     * `Socialite::fake()`.
     */
    public static function routes(): void
    {
        Route::name('auth.google.')
            ->prefix('auth/google')
            ->group(function (): void {
                Route::get('redirect', [self::class, 'redirect'])->name('redirect');
                Route::get('callback', [self::class, 'callback'])->name('callback');
            });
    }

    /**
     * Envía al usuario a Google. La URL de callback se resuelve por panel en
     * tiempo de ejecución (no queda fija en config/services.php) para que
     * admin.* y clientes.* usen cada uno la suya.
     */
    public function redirect(): RedirectResponse
    {
        $panel = Filament::getCurrentPanel();

        return Socialite::driver('google')
            ->redirectUrl($panel->route('auth.google.callback'))
            ->redirect();
    }

    /**
     * Recibe la vuelta de Google. Solo entra si el email ya existe como
     * usuario y ese usuario puede acceder a este panel puntual — nunca se
     * crea una cuenta nueva desde acá.
     */
    public function callback(Request $request): RedirectResponse
    {
        $panel = Filament::getCurrentPanel();

        try {
            $googleUser = Socialite::driver('google')
                ->redirectUrl($panel->route('auth.google.callback'))
                ->user();
        } catch (InvalidStateException) {
            return $this->rechazar($panel, 'No pudimos completar el acceso con Google. Intentá de nuevo.');
        }

        $user = User::query()->where('email', $googleUser->getEmail())->first();

        if ($user === null || ! $user->canAccessPanel($panel)) {
            return $this->rechazar($panel, 'Esa cuenta de Google no tiene acceso a este panel.');
        }

        if ($user->google_id === null) {
            $user->forceFill(['google_id' => $googleUser->getId()])->saveQuietly();
        }

        Auth::guard($panel->getAuthGuard())->login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended($panel->getUrl());
    }

    private function rechazar(Panel $panel, string $mensaje): RedirectResponse
    {
        Notification::make()
            ->danger()
            ->title('Acceso denegado')
            ->body($mensaje)
            ->persistent()
            ->send();

        return redirect()->to($panel->getLoginUrl());
    }
}
