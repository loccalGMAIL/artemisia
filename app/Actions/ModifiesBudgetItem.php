<?php

namespace App\Actions;

use App\Enums\BudgetHistoryField;
use App\Exceptions\BudgetNotEditableException;
use App\Models\BudgetHistory;
use App\Models\BudgetItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Common flow of every Action that changes one existing item of a budget: the budget must be
 * draft or sent (RF-48), the totals are recalculated (RF-35) and the change is recorded with
 * its previous and new value (RF-33). Changing nothing records nothing.
 */
abstract class ModifiesBudgetItem
{
    public function __construct(protected readonly RecalculateBudgetTotalsAction $recalculate) {}

    /**
     * @param  array<string, mixed>  $changes
     *
     * @throws BudgetNotEditableException
     */
    protected function apply(BudgetItem $item, array $changes, User $actor): BudgetItem
    {
        $budget = $item->budget;

        $budget->assertEditable();

        $before = $item->snapshot();

        $item->fill($changes);

        $after = $item->snapshot();

        if ($after === $before) {
            return $item;
        }

        return DB::transaction(function () use ($item, $budget, $before, $after, $actor): BudgetItem {
            $item->save();

            $this->recalculate->handle($budget);

            BudgetHistory::query()->create([
                'budget_id' => $budget->id,
                'field' => BudgetHistoryField::ItemUpdated,
                'old_value' => $before,
                'new_value' => $after,
                'author_id' => $actor->id,
            ]);

            return $item;
        });
    }
}
