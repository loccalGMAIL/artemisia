<?php

namespace App\Policies;

use App\Models\Produccion;
use App\Models\User;

class ProduccionPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_produccion');
    }

    /**
     * Determine whether the user can view the model. Defensa en profundidad:
     * el permiso `view_produccion` solo dice que el rol puede ver
     * producciones en general, así que a un usuario cliente además se le
     * exige ser el dueño (mismo control que
     * `ProduccionResource::getEloquentQuery()` en el panel de clientes, ver
     * .ai/rules/filament.md).
     */
    public function view(User $user, Produccion $produccion): bool
    {
        if (! $user->can('view_produccion')) {
            return false;
        }

        if ($user->hasRole('cliente')) {
            return $produccion->presupuesto->cliente_id === $user->cliente_id;
        }

        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_produccion');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Produccion $produccion): bool
    {
        return $user->can('update_produccion');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Produccion $produccion): bool
    {
        return $user->can('delete_produccion');
    }

    /**
     * Determine whether the user can bulk-delete (Filament's `DeleteBulkAction`).
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_produccion');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Produccion $produccion): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Produccion $produccion): bool
    {
        return false;
    }
}
