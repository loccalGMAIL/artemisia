<?php

namespace App\Listeners;

use Filament\Facades\Filament;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Spatie\Permission\Events\PermissionAttachedEvent;
use Spatie\Permission\Events\PermissionDetachedEvent;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\Events\RoleDetachedEvent;

/**
 * Registra en el log de actividad los eventos de autenticación de Laravel
 * ("auth" — por contraseña o por Google, ver GoogleLoginController) y los
 * cambios de roles/permisos de spatie/laravel-permission ("accesos"). Estas
 * altas/bajas pasan por tablas pivot, así que no disparan eventos de modelo
 * (`LogsActivity` no las vería solo) — de ahí que necesiten sus propios
 * listeners, registrados a mano en AppServiceProvider::boot().
 */
class RegistrarActividadDeAcceso
{
    public function handleLogin(Login $event): void
    {
        activity('auth')
            ->causedBy($event->user)
            ->withProperties([
                'guard' => $event->guard,
                'remember' => $event->remember,
                'panel' => Filament::getCurrentPanel()?->getId(),
            ])
            ->event('login')
            ->log('Inicio de sesión');
    }

    public function handleLogout(Logout $event): void
    {
        activity('auth')
            ->causedBy($event->user)
            ->withProperties([
                'guard' => $event->guard,
                'panel' => Filament::getCurrentPanel()?->getId(),
            ])
            ->event('logout')
            ->log('Cierre de sesión');
    }

    public function handleFailed(Failed $event): void
    {
        activity('auth')
            ->causedBy($event->user)
            ->withProperties([
                // Nunca loguear $credentials completo: trae la contraseña en texto plano.
                ...Arr::only($event->credentials, ['email']),
                'guard' => $event->guard,
                'panel' => Filament::getCurrentPanel()?->getId(),
            ])
            ->event('failed')
            ->log('Intento de acceso fallido');
    }

    public function handleRoleAttached(RoleAttachedEvent $event): void
    {
        $this->logCambioDeRolesOPermisos($event->model, 'rol_asignado', 'Rol asignado', $event->rolesOrIds);
    }

    public function handleRoleDetached(RoleDetachedEvent $event): void
    {
        $this->logCambioDeRolesOPermisos($event->model, 'rol_removido', 'Rol removido', $event->rolesOrIds);
    }

    public function handlePermissionAttached(PermissionAttachedEvent $event): void
    {
        $this->logCambioDeRolesOPermisos($event->model, 'permiso_asignado', 'Permiso asignado', $event->permissionsOrIds);
    }

    public function handlePermissionDetached(PermissionDetachedEvent $event): void
    {
        $this->logCambioDeRolesOPermisos($event->model, 'permiso_removido', 'Permiso removido', $event->permissionsOrIds);
    }

    /**
     * @param  Model  $sujeto  El User o Role que recibió/perdió el rol o permiso.
     * @param  mixed  $rolesOPermisos  ids, nombres o modelos — Spatie no normaliza el tipo antes de emitir el evento.
     */
    private function logCambioDeRolesOPermisos(Model $sujeto, string $evento, string $descripcion, mixed $rolesOPermisos): void
    {
        activity('accesos')
            ->causedBy(auth()->user())
            ->performedOn($sujeto)
            ->withProperties(['valores' => collect(Arr::wrap($rolesOPermisos))->map(fn (mixed $v): mixed => $v instanceof Model ? $v->getKey() : $v)->all()])
            ->event($evento)
            ->log($descripcion);
    }
}
