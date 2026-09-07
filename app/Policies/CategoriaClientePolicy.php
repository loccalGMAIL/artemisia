<?php

namespace App\Policies;

use App\Models\CategoriaCliente;
use App\Models\User;

class CategoriaClientePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_categoria_cliente');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, CategoriaCliente $categoriaCliente): bool
    {
        return $user->can('view_categoria_cliente');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_categoria_cliente');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, CategoriaCliente $categoriaCliente): bool
    {
        return $user->can('update_categoria_cliente');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, CategoriaCliente $categoriaCliente): bool
    {
        return $user->can('delete_categoria_cliente');
    }

    /**
     * Determine whether the user can bulk-delete (Filament's `DeleteBulkAction`).
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_categoria_cliente');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, CategoriaCliente $categoriaCliente): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, CategoriaCliente $categoriaCliente): bool
    {
        return false;
    }
}
