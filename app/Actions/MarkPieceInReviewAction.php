<?php

namespace App\Actions;

use App\Enums\PieceStatus;
use App\Exceptions\InvalidPieceTransitionException;
use App\Models\Piece;
use App\Models\User;

class MarkPieceInReviewAction extends ChangesPieceStatus
{
    /**
     * Sends a piece in production to internal review (RF-21).
     *
     * @throws InvalidPieceTransitionException
     */
    public function handle(Piece $piece, User $actor): Piece
    {
        return $this->moveTo($piece, PieceStatus::InReview, [], $actor);
    }

    protected function allowedFrom(): array
    {
        return [PieceStatus::InProduction];
    }
}
