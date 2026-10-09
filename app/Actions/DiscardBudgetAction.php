<?php

namespace App\Actions;

use App\Enums\BudgetStatus;
use App\Exceptions\BudgetNotDiscardableException;
use App\Models\Budget;
use App\Models\User;

class DiscardBudgetAction
{
    /**
     * Discards a draft: it is soft deleted, so it disappears from the list but it is kept with
     * its items and its history (RF-51, RF-52, RF-53). Nothing is ever erased.
     *
     * @throws BudgetNotDiscardableException
     */
    public function handle(Budget $budget, User $actor): void
    {
        if ($budget->trashed()) {
            return;
        }

        if ($budget->status !== BudgetStatus::Draft) {
            throw new BudgetNotDiscardableException;
        }

        $budget->delete();
    }
}
