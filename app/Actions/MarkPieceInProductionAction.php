<?php

namespace App\Actions;

use App\Enums\PieceStatus;
use App\Exceptions\InvalidPieceTransitionException;
use App\Models\Piece;
use App\Models\User;

class MarkPieceInProductionAction extends ChangesPieceStatus
{
    /**
     * Starts the production of a pending piece (RF-20).
     *
     * @throws InvalidPieceTransitionException
     */
    public function handle(Piece $piece, User $actor): Piece
    {
        return $this->moveTo($piece, PieceStatus::InProduction, [], $actor);
    }

    protected function allowedFrom(): array
    {
        return [PieceStatus::Pending];
    }
}
