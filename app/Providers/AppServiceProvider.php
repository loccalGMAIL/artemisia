<?php

namespace App\Providers;

use App\Enums\AccessPortal;
use App\Models\AccessLog;
use App\Models\AccountHistory;
use App\Models\User;
use App\Policies\AccessLogPolicy;
use App\Policies\AccountPolicy;
use Filament\Facades\Filament;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The emailed link opens the password screen of the account's own portal (plan D-11).
        ResetPassword::createUrlUsing(
            fn (User $user, string $token): string => Filament::getPanel(AccessPortal::forAccount($user)->value)
                ->getResetPasswordUrl($token, $user),
        );

        Gate::policy(User::class, AccountPolicy::class);
        Gate::policy(AccessLog::class, AccessLogPolicy::class);
        Gate::policy(AccountHistory::class, AccessLogPolicy::class);
    }
}
