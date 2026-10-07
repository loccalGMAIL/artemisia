<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cuts the session of an account that was deactivated while signed in (RF-33).
 * It reads the flag from the database on every request, so it does not depend
 * on the user instance cached in the session guard.
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $this->isStillActive($user)) {
            Filament::auth()->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect(Filament::getLoginUrl());
        }

        return $next($request);
    }

    private function isStillActive(User $user): bool
    {
        return User::query()
            ->whereKey($user->getAuthIdentifier())
            ->where('is_active', true)
            ->exists();
    }
}
