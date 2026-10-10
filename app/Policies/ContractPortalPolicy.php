<?php

namespace App\Policies;

use App\Models\Contract;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * What a client account may see of the contracts in its portal: only those of the client it is
 * linked to (RF-97). The portal is read only, so there is no ability to change anything.
 */
class ContractPortalPolicy
{
    public function view(User $user, Contract $contract): Response
    {
        if (! $user->hasRole('client')) {
            return Response::deny(__('auth.insufficient_permission'));
        }

        if ($user->client_id === null) {
            return Response::deny(__('clients.portal.not_enabled'));
        }

        if ($contract->client_id !== $user->client_id) {
            return Response::deny(__('auth.insufficient_permission'));
        }

        return Response::allow();
    }
}
