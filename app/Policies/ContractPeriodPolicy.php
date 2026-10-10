<?php

namespace App\Policies;

use App\Models\ContractPeriod;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Periods are handled by admin and staff alike (RF-98). `update` covers the extras, the
 * discounts and the invoice reference; registering and voiding payments is PaymentPolicy.
 * A period is never deleted.
 */
class ContractPeriodPolicy
{
    public function viewAny(User $user): Response
    {
        return $this->staffOnly($user);
    }

    public function view(User $user, ContractPeriod $period): Response
    {
        return $this->staffOnly($user);
    }

    public function update(User $user, ContractPeriod $period): Response
    {
        return $this->staffOnly($user);
    }

    public function delete(User $user, ContractPeriod $period): Response
    {
        return Response::deny(__('contracts.validation.period_not_deletable'));
    }

    private function staffOnly(User $user): Response
    {
        return $user->hasAnyRole(['admin', 'staff'])
            ? Response::allow()
            : Response::deny(__('auth.insufficient_permission'));
    }
}
