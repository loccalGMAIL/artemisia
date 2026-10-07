<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * Applies to AccessLog and AccountHistory: only an admin reads them (RF-39), and
 * nobody creates, edits or deletes a record from the interface (RF-35, RNF-6).
 */
class AccessLogPolicy
{
    public function viewAny(User $user): Response
    {
        return $this->adminOnly($user);
    }

    public function view(User $user, Model $record): Response
    {
        return $this->adminOnly($user);
    }

    public function create(User $user): Response
    {
        return $this->readOnly();
    }

    public function update(User $user, Model $record): Response
    {
        return $this->readOnly();
    }

    public function delete(User $user, Model $record): Response
    {
        return $this->readOnly();
    }

    public function restore(User $user, Model $record): Response
    {
        return $this->readOnly();
    }

    public function forceDelete(User $user, Model $record): Response
    {
        return $this->readOnly();
    }

    private function adminOnly(User $user): Response
    {
        return $user->hasRole('admin')
            ? Response::allow()
            : Response::deny(__('auth.insufficient_permission'));
    }

    private function readOnly(): Response
    {
        return Response::deny(__('auth.record_read_only'));
    }
}
