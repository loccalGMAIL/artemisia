<?php

namespace App\Policies;

use App\Models\CostoProveedor;
use App\Models\User;

class CostoProveedorPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_costo_proveedor');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, CostoProveedor $costoProveedor): bool
    {
        return $user->can('view_costo_proveedor');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_costo_proveedor');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, CostoProveedor $costoProveedor): bool
    {
        return $user->can('update_costo_proveedor');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, CostoProveedor $costoProveedor): bool
    {
        return $user->can('delete_costo_proveedor');
    }

    /**
     * Determine whether the user can bulk-delete (Filament's `DeleteBulkAction`).
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_costo_proveedor');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, CostoProveedor $costoProveedor): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, CostoProveedor $costoProveedor): bool
    {
        return false;
    }
}
