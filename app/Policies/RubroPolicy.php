<?php

namespace App\Policies;

use App\Models\Rubro;
use App\Models\User;

class RubroPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_rubro');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Rubro $rubro): bool
    {
        return $user->can('view_rubro');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_rubro');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Rubro $rubro): bool
    {
        return $user->can('update_rubro');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Rubro $rubro): bool
    {
        return $user->can('delete_rubro');
    }

    /**
     * Determine whether the user can bulk-delete (Filament's `DeleteBulkAction`).
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_rubro');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Rubro $rubro): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Rubro $rubro): bool
    {
        return false;
    }
}
