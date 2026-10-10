<?php

namespace App\Actions;

use App\Enums\PieceApprovalResolution;
use App\Enums\PieceStatus;
use App\Exceptions\InvalidPieceTransitionException;
use App\Models\Piece;
use App\Models\PieceApprovalSubmission;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Common flow of the client's answer to a submission: only the linked account may give it,
 * only to the pending submission of a piece in client approval, and the piece moves on with
 * the answer recorded in both the submission and the history (RF-32 to RF-34, RF-47, RF-48).
 */
abstract class ResolvesPieceSubmission extends ChangesPieceStatus
{
    /**
     * @return array<int, PieceStatus>
     */
    protected function allowedFrom(): array
    {
        return [PieceStatus::ClientApproval];
    }

    /**
     * @throws AuthorizationException
     * @throws InvalidPieceTransitionException
     */
    protected function resolve(
        PieceApprovalSubmission $submission,
        User $clientAccount,
        string $ability,
        PieceApprovalResolution $resolution,
        PieceStatus $next,
        ?string $rejectionReason,
    ): Piece {
        Gate::forUser($clientAccount)->authorize($ability, $submission);

        return DB::transaction(function () use ($submission, $clientAccount, $resolution, $next, $rejectionReason): Piece {
            $submission->forceFill([
                'resolution' => $resolution,
                'resolved_by' => $clientAccount->id,
                'resolved_at' => now(),
                'rejection_reason' => $rejectionReason,
            ])->save();

            return $this->moveTo($submission->piece, $next, [], $clientAccount);
        });
    }
}
