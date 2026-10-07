<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cuts the session of an account that was deactivated while signed in (RF-33), or whose
 * link to a client was removed or changed while signed in (RF-42). It reads the account
 * from the database on every request, so it does not depend on the instance cached by
 * the session guard.
 */
class EnsureAccountIsActive
{
    private const SESSION_CLIENT_KEY = 'account.client_id';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $this->sessionIsStillValid($request, $user)) {
            Filament::auth()->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect(Filament::getLoginUrl());
        }

        return $next($request);
    }

    private function sessionIsStillValid(Request $request, User $user): bool
    {
        $current = User::query()->whereKey($user->getAuthIdentifier())->first(['id', 'is_active', 'client_id']);

        if ($current === null || ! $current->is_active) {
            return false;
        }

        // The session remembers the client link the account signed in with. Losing or
        // changing it cuts the session; an account that never had a link is not affected.
        $session = $request->session();

        if (! $session->has(self::SESSION_CLIENT_KEY)) {
            $session->put(self::SESSION_CLIENT_KEY, $current->client_id);

            return true;
        }

        $remembered = $session->get(self::SESSION_CLIENT_KEY);

        return $remembered === null || $remembered === $current->client_id;
    }
}
