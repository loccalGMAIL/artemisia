<?php

namespace App\Policies;

use App\Models\Contract;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Contracts are handled by admin and staff alike; a client account never gets in through the
 * staff portal (RF-98). Whether an action fits the contract's state is the Actions' business,
 * not the policy's. Contracts are cancelled, never deleted (RF-34).
 */
class ContractPolicy
{
    public function viewAny(User $user): Response
    {
        return $this->staffOnly($user);
    }

    public function view(User $user, Contract $contract): Response
    {
        return $this->staffOnly($user);
    }

    public function create(User $user): Response
    {
        return $this->staffOnly($user);
    }

    public function update(User $user, Contract $contract): Response
    {
        return $this->staffOnly($user);
    }

    public function cancel(User $user, Contract $contract): Response
    {
        return $this->staffOnly($user);
    }

    public function reactivate(User $user, Contract $contract): Response
    {
        return $this->staffOnly($user);
    }

    public function delete(User $user, Contract $contract): Response
    {
        return Response::deny(__('contracts.validation.contract_not_deletable'));
    }

    public function restore(User $user, Contract $contract): Response
    {
        return Response::deny(__('contracts.validation.contract_not_deletable'));
    }

    public function forceDelete(User $user, Contract $contract): Response
    {
        return Response::deny(__('contracts.validation.contract_not_deletable'));
    }

    private function staffOnly(User $user): Response
    {
        return $user->hasAnyRole(['admin', 'staff'])
            ? Response::allow()
            : Response::deny(__('auth.insufficient_permission'));
    }
}
