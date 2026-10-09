<?php

namespace App\Actions;

use App\Enums\PieceHistoryField;
use App\Enums\PieceStatus;
use App\Models\Budget;
use App\Models\Piece;
use App\Models\PieceHistory;
use App\Models\User;

/**
 * Common flow of every piece creation: it starts pending and its first history entry records
 * who created it (RF-19, RF-38). The caller owns the transaction and the validation.
 */
abstract class CreatesPiece
{
    /**
     * @param  array{budget_item_id?: int|null, name: string, description?: string|null, work_category_id: int, due_date?: string|null}  $values
     */
    protected function storePiece(Budget $budget, array $values, User $author): Piece
    {
        $piece = Piece::query()->create([
            'budget_id' => $budget->id,
            'budget_item_id' => $values['budget_item_id'] ?? null,
            'name' => $values['name'],
            'description' => $values['description'] ?? null,
            'work_category_id' => $values['work_category_id'],
            'status' => PieceStatus::Pending,
            'due_date' => $values['due_date'] ?? null,
            'created_by' => $author->id,
        ]);

        PieceHistory::query()->create([
            'piece_id' => $piece->id,
            'field' => PieceHistoryField::Created,
            'old_value' => null,
            'new_value' => $piece->creationSnapshot(),
            'author_id' => $author->id,
        ]);

        return $piece;
    }
}
