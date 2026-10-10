<?php

namespace App\Actions;

use App\Enums\PieceStatus;
use App\Exceptions\PieceNotDiscardableException;
use App\Models\Piece;
use App\Models\User;

class DiscardPieceAction
{
    /**
     * Discards a pending piece: it is soft deleted, so it disappears from the list but it is
     * kept with its history and submissions (RF-26, RF-27, RF-40). Nothing is ever erased.
     *
     * @throws PieceNotDiscardableException
     */
    public function handle(Piece $piece, User $actor): void
    {
        if ($piece->trashed()) {
            return;
        }

        if ($piece->status !== PieceStatus::Pending) {
            throw new PieceNotDiscardableException;
        }

        $piece->delete();
    }
}
