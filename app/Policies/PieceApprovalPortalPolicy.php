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
    /**
     * The file of a submission is downloaded by the linked account while the piece is visible
     * in the portal, and always for the submissions that account answered itself (RF-37, RF-45).
     */
    public function download(User $user, PieceApprovalSubmission $submission): Response
    {
        $denied = $this->linkedClientOnly($user, $submission);

        if ($denied !== null) {
            return $denied;
        }

        if ($submission->resolved_by === $user->id || in_array($submission->piece->status, PieceStatus::visibleToClient(), true)) {
            return Response::allow();
        }

        return Response::deny(__('auth.insufficient_permission'));
    }

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
        $denied = $this->linkedClientOnly($user, $submission);

        if ($denied !== null) {
            return $denied;
        }

        if ($submission->piece->status !== PieceStatus::ClientApproval || ! $submission->isPending()) {
            return Response::deny(__('auth.insufficient_permission'));
        }

        return Response::allow();
    }

    /**
     * Only a client account linked to the client of the piece's budget gets past this point.
     */
    private function linkedClientOnly(User $user, PieceApprovalSubmission $submission): ?Response
    {
        if (! $user->hasRole('client')) {
            return Response::deny(__('auth.insufficient_permission'));
        }

        if ($user->client_id === null) {
            return Response::deny(__('clients.portal.not_enabled'));
        }

        if ($submission->piece->budget->client_id !== $user->client_id) {
            return Response::deny(__('auth.insufficient_permission'));
        }

        return null;
    }
}
