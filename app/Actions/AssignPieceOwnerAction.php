<?php

namespace App\Actions;

use App\Enums\AccountRole;
use App\Enums\PieceHistoryField;
use App\Exceptions\InvalidPieceAssigneeException;
use App\Exceptions\PieceDeliveredException;
use App\Models\Piece;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AssignPieceOwnerAction extends ChangesPiece
{
    /**
     * Delegates the piece to an admin or staff account, or reassigns it: a piece has at most
     * one owner, and a delivered piece keeps the one it had (RF-11, RF-12, RF-14, RF-25).
     *
     * @throws InvalidPieceAssigneeException
     * @throws PieceDeliveredException
     */
    public function handle(Piece $piece, User $assignee, User $actor): Piece
    {
        $piece->assertNotDelivered();

        if (! $assignee->hasAnyRole([AccountRole::Admin->value, AccountRole::Staff->value])) {
            throw new InvalidPieceAssigneeException;
        }

        return DB::transaction(function () use ($piece, $assignee, $actor): Piece {
            $previous = $piece->assignee_id;

            $piece->forceFill(['assignee_id' => $assignee->id])->save();

            $this->recordChange(
                $piece,
                PieceHistoryField::AssigneeChanged,
                ['assignee_id' => $previous],
                ['assignee_id' => $assignee->id],
                $actor,
            );

            return $piece;
        });
    }
}
