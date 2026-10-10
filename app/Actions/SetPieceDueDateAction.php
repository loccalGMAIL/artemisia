<?php

namespace App\Actions;

use App\Enums\PieceHistoryField;
use App\Models\Piece;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class SetPieceDueDateAction extends ChangesPiece
{
    /**
     * Loads, changes or clears the committed delivery date, which is always optional
     * (RF-15, RF-16).
     */
    public function handle(Piece $piece, ?CarbonInterface $dueDate, User $actor): Piece
    {
        return DB::transaction(function () use ($piece, $dueDate, $actor): Piece {
            $previous = $piece->due_date?->toDateString();

            $piece->forceFill(['due_date' => $dueDate?->toDateString()])->save();

            $this->recordChange(
                $piece,
                PieceHistoryField::DueDateChanged,
                ['due_date' => $previous],
                ['due_date' => $piece->due_date?->toDateString()],
                $actor,
            );

            return $piece;
        });
    }
}
