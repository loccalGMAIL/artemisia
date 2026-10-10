<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Payments are registered and voided by admin and staff alike (RF-98). A payment is voided,
 * never deleted (RF-80).
 */
class PaymentPolicy
{
    public function viewAny(User $user): Response
    {
        return $this->staffOnly($user);
    }

    public function view(User $user, Payment $payment): Response
    {
        return $this->staffOnly($user);
    }

    public function create(User $user): Response
    {
        return $this->staffOnly($user);
    }

    public function void(User $user, Payment $payment): Response
    {
        return $this->staffOnly($user);
    }

    public function delete(User $user, Payment $payment): Response
    {
        return Response::deny(__('contracts.validation.payment_not_deletable'));
    }

    public function restore(User $user, Payment $payment): Response
    {
        return Response::deny(__('contracts.validation.payment_not_deletable'));
    }

    public function forceDelete(User $user, Payment $payment): Response
    {
        return Response::deny(__('contracts.validation.payment_not_deletable'));
    }

    private function staffOnly(User $user): Response
    {
        return $user->hasAnyRole(['admin', 'staff'])
            ? Response::allow()
            : Response::deny(__('auth.insufficient_permission'));
    }
}
