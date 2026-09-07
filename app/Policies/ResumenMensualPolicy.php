<?php

namespace App\Policies;

use App\Models\ResumenMensual;
use App\Models\User;

class ResumenMensualPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_resumen_mensual');
    }

    /**
     * Determine whether the user can view the model. Defensa en profundidad:
     * el permiso `view_resumen_mensual` solo dice que el rol puede ver
     * resúmenes en general, así que a un usuario cliente además se le exige
     * ser el dueño (mismo control que
     * `ResumenMensualResource::getEloquentQuery()` en el panel de clientes,
     * ver .ai/rules/filament.md).
     */
    public function view(User $user, ResumenMensual $resumenMensual): bool
    {
        if (! $user->can('view_resumen_mensual')) {
            return false;
        }

        if ($user->hasRole('cliente')) {
            return $resumenMensual->contrato->presupuesto->cliente_id === $user->cliente_id;
        }

        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_resumen_mensual');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ResumenMensual $resumenMensual): bool
    {
        return $user->can('update_resumen_mensual');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ResumenMensual $resumenMensual): bool
    {
        return $user->can('delete_resumen_mensual');
    }

    /**
     * Determine whether the user can bulk-delete (Filament's `DeleteBulkAction`).
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_resumen_mensual');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ResumenMensual $resumenMensual): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ResumenMensual $resumenMensual): bool
    {
        return false;
    }
}
