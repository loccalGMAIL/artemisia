<?php

namespace App\Actions;

use App\Enums\PieceHistoryField;
use App\Enums\PieceStatus;
use App\Exceptions\InvalidPieceTransitionException;
use App\Models\Piece;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Common flow of every state change of a piece: only the transitions of PieceStatus are
 * allowed, and each one is recorded with the previous and new state (RF-18, RF-38).
 */
abstract class ChangesPieceStatus extends ChangesPiece
{
    /**
     * The states this Action may start from. The same target can be reached by two Actions
     * (approving and rejecting both leave the approval step), and each accepts only its origin.
     *
     * @return array<int, PieceStatus>
     */
    abstract protected function allowedFrom(): array;

    /**
     * @param  array<string, mixed>  $fields  other columns that change together with the status
     *
     * @throws InvalidPieceTransitionException
     */
    protected function moveTo(Piece $piece, PieceStatus $next, array $fields, User $actor): Piece
    {
        if (! in_array($piece->status, $this->allowedFrom(), true) || ! $piece->status->canMoveTo($next)) {
            throw new InvalidPieceTransitionException($piece->status, $next);
        }

        return DB::transaction(function () use ($piece, $next, $fields, $actor): Piece {
            $previous = $piece->status;

            $piece->forceFill([...$fields, 'status' => $next])->save();

            $this->recordChange(
                $piece,
                PieceHistoryField::StatusChanged,
                ['status' => $previous->value],
                ['status' => $next->value],
                $actor,
            );

            return $piece;
        });
    }
}
