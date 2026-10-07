<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Staff side of clients: admin and staff have the same permissions. A client account
 * never gets through here; its own access goes through ClientPortalPolicy. Clients are
 * archived, never deleted (RF-34), so delete is denied to everyone.
 */
class ClientPolicy
{
    public function viewAny(User $user): Response
    {
        return $this->staffOnly($user);
    }

    public function view(User $user, Client $client): Response
    {
        return $this->staffOnly($user);
    }

    public function create(User $user): Response
    {
        return $this->staffOnly($user);
    }

    public function update(User $user, Client $client): Response
    {
        return $this->staffOnly($user);
    }

    public function archive(User $user, Client $client): Response
    {
        return $this->staffOnly($user);
    }

    public function restore(User $user, Client $client): Response
    {
        return $this->staffOnly($user);
    }

    public function linkAccount(User $user, Client $client): Response
    {
        return $this->staffOnly($user);
    }

    public function unlinkAccount(User $user, Client $client): Response
    {
        return $this->staffOnly($user);
    }

    public function export(User $user): Response
    {
        return $this->staffOnly($user);
    }

    public function delete(User $user, Client $client): Response
    {
        return Response::deny(__('clients.validation.client_not_deletable'));
    }

    public function forceDelete(User $user, Client $client): Response
    {
        return Response::deny(__('clients.validation.client_not_deletable'));
    }

    private function staffOnly(User $user): Response
    {
        return $user->hasAnyRole(['admin', 'staff'])
            ? Response::allow()
            : Response::deny(__('auth.insufficient_permission'));
    }
}
