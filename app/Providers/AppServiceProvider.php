<?php

namespace App\Providers;

use App\Models\AccessLog;
use App\Models\AccountHistory;
use App\Models\User;
use App\Policies\AccessLogPolicy;
use App\Policies\AccountPolicy;
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
        Gate::policy(User::class, AccountPolicy::class);
        Gate::policy(AccessLog::class, AccessLogPolicy::class);
        Gate::policy(AccountHistory::class, AccessLogPolicy::class);
    }
}
