<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * What a client account may do in the client portal, always limited to the client it is
 * linked to. It deliberately offers no ability to change identification, status or
 * archiving (RF-60).
 */
class ClientPortalPolicy
{
    public function view(User $user, ?Client $client = null): Response
    {
        return $this->ownClientOnly($user, $client);
    }

    public function updateAddress(User $user, ?Client $client = null): Response
    {
        return $this->ownClientOnly($user, $client);
    }

    public function updateContacts(User $user, ?Client $client = null): Response
    {
        return $this->ownClientOnly($user, $client);
    }

    private function ownClientOnly(User $user, ?Client $client = null): Response
    {
        if (! $user->hasRole('client')) {
            return Response::deny(__('auth.insufficient_permission'));
        }

        if ($user->client_id === null) {
            return Response::deny(__('clients.portal.not_enabled'));
        }

        if ($client === null || $client->id !== $user->client_id) {
            return Response::deny(__('auth.insufficient_permission'));
        }

        if ($client->trashed()) {
            return Response::deny(__('clients.portal.archived'));
        }

        return Response::allow();
    }
}
