<?php

namespace App\Actions;

use App\Enums\PieceStatus;
use App\Exceptions\InvalidPieceTransitionException;
use App\Models\Piece;
use App\Models\User;

class MarkPieceDeliveredAction extends ChangesPieceStatus
{
    /**
     * Delivers an approved piece; from then on it admits no more changes (RF-24, RF-25).
     *
     * @throws InvalidPieceTransitionException
     */
    public function handle(Piece $piece, User $actor): Piece
    {
        return $this->moveTo($piece, PieceStatus::Delivered, [], $actor);
    }

    protected function allowedFrom(): array
    {
        return [PieceStatus::Approved];
    }
}
