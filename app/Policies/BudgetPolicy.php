<?php

namespace App\Policies;

use App\Models\Budget;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Budgets are handled by admin and staff alike; a client account never gets in (RF-60).
 * Whether an action fits the budget's state is the Actions' business, not the policy's.
 * Budgets are discarded, never deleted (RF-53).
 */
class BudgetPolicy
{
    public function viewAny(User $user): Response
    {
        return $this->staffOnly($user);
    }

    public function view(User $user, Budget $budget): Response
    {
        return $this->staffOnly($user);
    }

    public function create(User $user): Response
    {
        return $this->staffOnly($user);
    }

    public function update(User $user, Budget $budget): Response
    {
        return $this->staffOnly($user);
    }

    public function send(User $user, Budget $budget): Response
    {
        return $this->staffOnly($user);
    }

    public function accept(User $user, Budget $budget): Response
    {
        return $this->staffOnly($user);
    }

    public function reject(User $user, Budget $budget): Response
    {
        return $this->staffOnly($user);
    }

    public function revert(User $user, Budget $budget): Response
    {
        return $this->staffOnly($user);
    }

    public function discard(User $user, Budget $budget): Response
    {
        return $this->staffOnly($user);
    }

    public function downloadPdf(User $user, Budget $budget): Response
    {
        return $this->staffOnly($user);
    }

    public function delete(User $user, Budget $budget): Response
    {
        return Response::deny(__('budgets.validation.budget_not_deletable'));
    }

    public function forceDelete(User $user, Budget $budget): Response
    {
        return Response::deny(__('budgets.validation.budget_not_deletable'));
    }

    private function staffOnly(User $user): Response
    {
        return $user->hasAnyRole(['admin', 'staff'])
            ? Response::allow()
            : Response::deny(__('auth.insufficient_permission'));
    }
}
