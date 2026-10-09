<?php

namespace App\Actions;

use App\Enums\BudgetHistoryField;
use App\Enums\BudgetStatus;
use App\Exceptions\InvalidBudgetTransitionException;
use App\Models\Budget;
use App\Models\BudgetHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Common flow of every state change of a budget: only the transitions of BudgetStatus are
 * allowed, and each one is recorded with the previous and new state (RF-42, RF-50).
 */
abstract class ChangesBudgetStatus
{
    /**
     * The states this Action may start from. The same target can be reached by two Actions
     * (sending a draft, reverting an answer), and each one accepts only its own origin.
     *
     * @return array<int, BudgetStatus>
     */
    abstract protected function allowedFrom(): array;

    /**
     * @param  array<string, mixed>  $fields  other columns that change together with the status
     *
     * @throws InvalidBudgetTransitionException
     */
    protected function moveTo(Budget $budget, BudgetStatus $next, array $fields, User $actor): Budget
    {
        $this->assertMoveAllowed($budget, $next);

        return DB::transaction(function () use ($budget, $next, $fields, $actor): Budget {
            $before = $budget->statusSnapshot();

            $budget->forceFill([...$fields, 'status' => $next])->save();

            BudgetHistory::query()->create([
                'budget_id' => $budget->id,
                'field' => BudgetHistoryField::StatusChanged,
                'old_value' => $before,
                'new_value' => $budget->statusSnapshot(),
                'author_id' => $actor->id,
            ]);

            return $budget;
        });
    }

    /**
     * @throws InvalidBudgetTransitionException
     */
    protected function assertMoveAllowed(Budget $budget, BudgetStatus $next): void
    {
        if (! in_array($budget->status, $this->allowedFrom(), true) || ! $budget->status->canMoveTo($next)) {
            throw new InvalidBudgetTransitionException($budget->status, $next);
        }
    }
}
