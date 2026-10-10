<?php

namespace App\Policies;

use App\Models\Piece;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Pieces are handled by admin and staff alike; a client account never gets in through the
 * staff portal (its own answers go through PieceApprovalPortalPolicy). Whether an action fits
 * the piece's state is the Actions' business, not the policy's. Pieces are discarded, never
 * deleted (RF-40).
 */
class PiecePolicy
{
    public function viewAny(User $user): Response
    {
        return $this->staffOnly($user);
    }

    public function view(User $user, Piece $piece): Response
    {
        return $this->staffOnly($user);
    }

    public function create(User $user): Response
    {
        return $this->staffOnly($user);
    }

    public function update(User $user, Piece $piece): Response
    {
        return $this->staffOnly($user);
    }

    public function discard(User $user, Piece $piece): Response
    {
        return $this->staffOnly($user);
    }

    public function delete(User $user, Piece $piece): Response
    {
        return Response::deny(__('pieces.validation.not_deletable'));
    }

    public function restore(User $user, Piece $piece): Response
    {
        return Response::deny(__('pieces.validation.not_deletable'));
    }

    public function forceDelete(User $user, Piece $piece): Response
    {
        return Response::deny(__('pieces.validation.not_deletable'));
    }

    private function staffOnly(User $user): Response
    {
        return $user->hasAnyRole(['admin', 'staff'])
            ? Response::allow()
            : Response::deny(__('auth.insufficient_permission'));
    }
}
