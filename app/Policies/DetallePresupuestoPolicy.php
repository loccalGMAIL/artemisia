<?php

namespace App\Policies;

use App\Models\DetallePresupuesto;
use App\Models\User;

class DetallePresupuestoPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_detalle_presupuesto');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, DetallePresupuesto $detallePresupuesto): bool
    {
        return $user->can('view_detalle_presupuesto');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_detalle_presupuesto');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, DetallePresupuesto $detallePresupuesto): bool
    {
        return $user->can('update_detalle_presupuesto');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, DetallePresupuesto $detallePresupuesto): bool
    {
        return $user->can('delete_detalle_presupuesto');
    }

    /**
     * Determine whether the user can bulk-delete (Filament's `DeleteBulkAction`).
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_detalle_presupuesto');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, DetallePresupuesto $detallePresupuesto): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, DetallePresupuesto $detallePresupuesto): bool
    {
        return false;
    }
}
