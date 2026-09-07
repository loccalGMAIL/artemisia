<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class RolePolicy
{
    /**
     * Roles que decide `User::canAccessPanel()`. Renombrarlos o borrarlos
     * rompe el acceso a los paneles, así que quedan protegidos aunque el
     * usuario tenga permiso de editar/borrar roles.
     *
     * @var list<string>
     */
    private const ROLES_PROTEGIDOS = ['staff', 'cliente'];

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_role');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Role $role): bool
    {
        return $user->can('view_role');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_role');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Role $role): bool
    {
        return $user->can('update_role');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Role $role): bool
    {
        return $user->can('delete_role') && ! in_array($role->name, self::ROLES_PROTEGIDOS, strict: true);
    }

    /**
     * Determine whether the user can bulk-delete (Filament's `DeleteBulkAction`).
     * Los roles protegidos igual quedan a salvo: la tabla de roles usa
     * `authorizeIndividualRecords('delete')`, que llama a `delete()` por cada
     * registro seleccionado en vez de confiar solo en este permiso general.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_role');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Role $role): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Role $role): bool
    {
        return false;
    }
}
