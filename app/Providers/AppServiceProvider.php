<?php

namespace App\Providers;

use App\Listeners\RegistrarActividadDeAcceso;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Events\PermissionAttachedEvent;
use Spatie\Permission\Events\PermissionDetachedEvent;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\Events\RoleDetachedEvent;

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
        // App\Models\Role (que extiende Spatie\Permission\Models\Role, ver
        // config('permission.models.role')) vive en App\Models, así que el
        // autodescubrimiento de policies de Laravel encuentra
        // App\Policies\RolePolicy solo. No hace falta Gate::policy() acá.

        Event::listen(Login::class, [RegistrarActividadDeAcceso::class, 'handleLogin']);
        Event::listen(Logout::class, [RegistrarActividadDeAcceso::class, 'handleLogout']);
        Event::listen(Failed::class, [RegistrarActividadDeAcceso::class, 'handleFailed']);

        Event::listen(RoleAttachedEvent::class, [RegistrarActividadDeAcceso::class, 'handleRoleAttached']);
        Event::listen(RoleDetachedEvent::class, [RegistrarActividadDeAcceso::class, 'handleRoleDetached']);
        Event::listen(PermissionAttachedEvent::class, [RegistrarActividadDeAcceso::class, 'handlePermissionAttached']);
        Event::listen(PermissionDetachedEvent::class, [RegistrarActividadDeAcceso::class, 'handlePermissionDetached']);
    }
}
