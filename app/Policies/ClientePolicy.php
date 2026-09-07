<?php

namespace App\Policies;

use App\Models\Cliente;
use App\Models\User;

class ClientePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_cliente');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Cliente $cliente): bool
    {
        return $user->can('view_cliente');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_cliente');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Cliente $cliente): bool
    {
        return $user->can('update_cliente');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Cliente $cliente): bool
    {
        return $user->can('delete_cliente');
    }

    /**
     * Determine whether the user can bulk-delete (Filament's `DeleteBulkAction`).
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_cliente');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Cliente $cliente): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Cliente $cliente): bool
    {
        return false;
    }
}
