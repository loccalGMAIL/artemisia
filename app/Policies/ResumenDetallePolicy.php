<?php

namespace App\Policies;

use App\Models\ResumenDetalle;
use App\Models\User;

class ResumenDetallePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_resumen_detalle');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ResumenDetalle $resumenDetalle): bool
    {
        return $user->can('view_resumen_detalle');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_resumen_detalle');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ResumenDetalle $resumenDetalle): bool
    {
        return $user->can('update_resumen_detalle');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ResumenDetalle $resumenDetalle): bool
    {
        return $user->can('delete_resumen_detalle');
    }

    /**
     * Determine whether the user can bulk-delete (Filament's `DeleteBulkAction`).
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_resumen_detalle');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ResumenDetalle $resumenDetalle): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ResumenDetalle $resumenDetalle): bool
    {
        return false;
    }
}
