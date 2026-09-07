<?php

namespace App\Policies;

use App\Models\Contrato;
use App\Models\User;

class ContratoPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_contrato');
    }

    /**
     * Determine whether the user can view the model. Defensa en profundidad:
     * el permiso `view_contrato` solo dice que el rol puede ver contratos en
     * general, así que a un usuario cliente además se le exige ser el dueño
     * (mismo control que `ContratoResource::getEloquentQuery()` en el panel
     * de clientes, ver .ai/rules/filament.md).
     */
    public function view(User $user, Contrato $contrato): bool
    {
        if (! $user->can('view_contrato')) {
            return false;
        }

        if ($user->hasRole('cliente')) {
            return $contrato->presupuesto->cliente_id === $user->cliente_id;
        }

        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_contrato');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Contrato $contrato): bool
    {
        return $user->can('update_contrato');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Contrato $contrato): bool
    {
        return $user->can('delete_contrato');
    }

    /**
     * Determine whether the user can bulk-delete (Filament's `DeleteBulkAction`).
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_contrato');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Contrato $contrato): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Contrato $contrato): bool
    {
        return false;
    }
}
