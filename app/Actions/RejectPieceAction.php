<?php

namespace App\Actions;

use App\Enums\PieceApprovalResolution;
use App\Enums\PieceStatus;
use App\Models\Piece;
use App\Models\PieceApprovalSubmission;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RejectPieceAction extends ResolvesPieceSubmission
{
    /**
     * The linked client account rejects the pending submission, with or without a reason: the
     * piece goes back to production (RF-34, RF-35).
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(PieceApprovalSubmission $submission, User $clientAccount, ?string $reason): Piece
    {
        $reason = Validator::make(['rejection_reason' => $reason], [
            'rejection_reason' => ['nullable', 'string', 'max:500'],
        ], [
            'rejection_reason.max' => __('pieces.validation.rejection_reason_max'),
        ])->validate()['rejection_reason'];

        return $this->resolve(
            $submission,
            $clientAccount,
            'portal.pieces.reject',
            PieceApprovalResolution::Rejected,
            PieceStatus::InProduction,
            filled($reason) ? trim($reason) : null,
        );
    }
}
