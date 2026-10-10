<?php

namespace App\Actions;

use App\Exceptions\BudgetNotAcceptedException;
use App\Models\Budget;
use App\Models\Piece;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
     * @throws ValidationException
     */
    public function handle(Budget $budget, array $lines, User $author): Collection
    {
        $budget->assertAccepted();

        $lines = array_values($lines);

        $this->validateLines($budget, $lines);

        return DB::transaction(fn (): Collection => collect($lines)
            ->map(fn (array $line): Piece => $this->storePiece($budget, $line, $author))
            ->values());
    }

    /**
     * Every line needs a name and a work category, and a source item must belong to the budget.
     * The problems are reported as one error about the whole proposal.
     *
     * @param  list<array<string, mixed>>  $lines
     *
     * @throws ValidationException
     */
    private function validateLines(Budget $budget, array $lines): void
    {
        $validator = Validator::make(['lines' => $lines], [
            'lines.*.name' => ['required', 'string', 'max:150'],
            'lines.*.description' => ['nullable', 'string'],
            'lines.*.work_category_id' => ['required', 'integer', 'exists:work_categories,id'],
            'lines.*.budget_item_id' => ['nullable', 'integer', Rule::exists('budget_items', 'id')->where('budget_id', $budget->id)],
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages(['lines' => [__('pieces.validation.lines_invalid')]]);
        }
    }
}
