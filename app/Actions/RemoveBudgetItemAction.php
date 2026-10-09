<?php

namespace App\Actions;

use App\Enums\BudgetHistoryField;
use App\Exceptions\BudgetNotEditableException;
use App\Models\BudgetHistory;
use App\Models\BudgetItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RemoveBudgetItemAction
{
    public function __construct(private readonly RecalculateBudgetTotalsAction $recalculate) {}

    /**
     * Removes an item for good; its data stays as a snapshot in the history (RF-30, RF-32,
     * plan D-5).
     *
     * @throws BudgetNotEditableException
     */
    public function handle(BudgetItem $item, User $actor): void
    {
        $budget = $item->budget;

        $budget->assertEditable();

        DB::transaction(function () use ($item, $budget, $actor): void {
            $snapshot = $item->snapshot();

            $item->delete();

            $this->recalculate->handle($budget);

            BudgetHistory::query()->create([
                'budget_id' => $budget->id,
                'field' => BudgetHistoryField::ItemRemoved,
                'old_value' => $snapshot,
                'new_value' => ['removed' => true],
                'author_id' => $actor->id,
            ]);
        });
    }
}
