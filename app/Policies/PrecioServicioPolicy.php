<?php

namespace App\Policies;

use App\Models\PrecioServicio;
use App\Models\User;

class PrecioServicioPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_precio_servicio');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, PrecioServicio $precioServicio): bool
    {
        return $user->can('view_precio_servicio');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_precio_servicio');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, PrecioServicio $precioServicio): bool
    {
        return $user->can('update_precio_servicio');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PrecioServicio $precioServicio): bool
    {
        return $user->can('delete_precio_servicio');
    }

    /**
     * Determine whether the user can bulk-delete (Filament's `DeleteBulkAction`).
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_precio_servicio');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, PrecioServicio $precioServicio): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, PrecioServicio $precioServicio): bool
    {
        return false;
    }
}
