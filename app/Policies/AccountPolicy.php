<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Only an admin manages accounts and roles (RF-9). No account is ever deleted (RF-34).
 */
class AccountPolicy
{
    public function viewAny(User $user): Response
    {
        return $this->adminOnly($user);
    }

    public function view(User $user, User $target): Response
    {
        return $this->adminOnly($user);
    }

    public function create(User $user): Response
    {
        return $this->adminOnly($user);
    }

    public function update(User $user, User $target): Response
    {
        return $this->adminOnly($user);
    }

    public function changeRole(User $user, User $target): Response
    {
        return $this->adminOnly($user);
    }

    public function activate(User $user, User $target): Response
    {
        return $this->adminOnly($user);
    }

    public function deactivate(User $user, User $target): Response
    {
        return $this->adminOnly($user);
    }

    public function delete(User $user, User $target): Response
    {
        return Response::deny(__('auth.account_not_deletable'));
    }

    public function restore(User $user, User $target): Response
    {
        return Response::deny(__('auth.account_not_deletable'));
    }

    public function forceDelete(User $user, User $target): Response
    {
        return Response::deny(__('auth.account_not_deletable'));
    }

    private function adminOnly(User $user): Response
    {
        return $user->hasRole('admin')
            ? Response::allow()
            : Response::deny(__('auth.insufficient_permission'));
    }
}
