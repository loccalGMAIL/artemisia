<?php

namespace App\Policies;

use App\Models\Service;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * The catalog is managed by admin and staff alike; a client account never gets in (RF-60).
 * Services are deactivated, never deleted (RF-9).
 */
class ServicePolicy
{
    public function viewAny(User $user): Response
    {
        return $this->staffOnly($user);
    }

    public function view(User $user, Service $service): Response
    {
        return $this->staffOnly($user);
    }

    public function create(User $user): Response
    {
        return $this->staffOnly($user);
    }

    public function update(User $user, Service $service): Response
    {
        return $this->staffOnly($user);
    }

    public function toggleActive(User $user, Service $service): Response
    {
        return $this->staffOnly($user);
    }

    public function delete(User $user, Service $service): Response
    {
        return Response::deny(__('budgets.validation.service_not_deletable'));
    }

    public function forceDelete(User $user, Service $service): Response
    {
        return Response::deny(__('budgets.validation.service_not_deletable'));
    }

    private function staffOnly(User $user): Response
    {
        return $user->hasAnyRole(['admin', 'staff'])
            ? Response::allow()
            : Response::deny(__('auth.insufficient_permission'));
    }
}
