<?php

namespace App\Policies;

use App\Enums\PieceStatus;
use App\Models\PieceApprovalSubmission;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * What a client account may do with the pieces in its portal: approve or reject the pending
 * submission of a piece that waits for its answer, always limited to the client it is linked to
 * (RF-32, RF-47, RF-48). Admin and staff never use these abilities.
 */
class PieceApprovalPortalPolicy
{
    public function approve(User $user, PieceApprovalSubmission $submission): Response
    {
        return $this->resolve($user, $submission);
    }

    public function reject(User $user, PieceApprovalSubmission $submission): Response
    {
        return $this->resolve($user, $submission);
    }

    private function resolve(User $user, PieceApprovalSubmission $submission): Response
    {
        if (! $user->hasRole('client')) {
            return Response::deny(__('auth.insufficient_permission'));
        }

        if ($user->client_id === null) {
            return Response::deny(__('clients.portal.not_enabled'));
        }

        $piece = $submission->piece;

        if ($piece->budget->client_id !== $user->client_id) {
            return Response::deny(__('auth.insufficient_permission'));
        }

        if ($piece->status !== PieceStatus::ClientApproval || ! $submission->isPending()) {
            return Response::deny(__('auth.insufficient_permission'));
        }

        return Response::allow();
    }
}
