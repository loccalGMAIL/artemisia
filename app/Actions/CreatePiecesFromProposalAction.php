<?php

namespace App\Actions;

use App\Exceptions\BudgetNotAcceptedException;
use App\Models\Budget;
use App\Models\Piece;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CreatePiecesFromProposalAction extends CreatesPiece
{
    /**
     * Creates the pieces of a proposal the staff already edited: one pending piece per line.
     * Dividing an item quantity is several lines with the same budget_item_id, and a removed
     * line is simply not sent (RF-2, RF-3, RF-4, RF-19). Each piece starts its history (RF-38).
     *
     * @param  list<array{budget_item_id: int|null, name: string, work_category_id: int, description?: string|null}>  $lines
     * @return Collection<int, Piece>
     *
     * @throws BudgetNotAcceptedException
     */
    public function handle(Budget $budget, array $lines, User $author): Collection
    {
        $budget->assertAccepted();

        return DB::transaction(fn (): Collection => collect($lines)
            ->map(fn (array $line): Piece => $this->storePiece($budget, $line, $author))
            ->values());
    }
}
