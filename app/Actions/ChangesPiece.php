<?php

namespace App\Actions;

use App\Enums\PieceHistoryField;
use App\Models\Piece;
use App\Models\PieceHistory;
use App\Models\User;

/**
 * Common flow of every change to an existing piece: it is recorded with the previous and new
 * value, its author and the date (RF-38).
 */
abstract class ChangesPiece
{
    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>  $new
     */
    protected function recordChange(Piece $piece, PieceHistoryField $field, ?array $old, array $new, User $actor): void
    {
        PieceHistory::query()->create([
            'piece_id' => $piece->id,
            'field' => $field,
            'old_value' => $old,
            'new_value' => $new,
            'author_id' => $actor->id,
        ]);
    }
}
