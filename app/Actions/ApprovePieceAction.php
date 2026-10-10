<?php

namespace App\Actions;

use App\Enums\PieceApprovalResolution;
use App\Enums\PieceStatus;
use App\Models\Piece;
use App\Models\PieceApprovalSubmission;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class ApprovePieceAction extends ResolvesPieceSubmission
{
    /**
     * The linked client account approves the pending submission: the piece is approved and the
     * date of the approval is the submission's resolution date (RF-32, RF-33).
     *
     * @throws AuthorizationException
     */
    public function handle(PieceApprovalSubmission $submission, User $clientAccount): Piece
    {
        return $this->resolve(
            $submission,
            $clientAccount,
            'portal.pieces.approve',
            PieceApprovalResolution::Approved,
            PieceStatus::Approved,
            null,
        );
    }
}
