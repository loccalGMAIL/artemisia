<?php

namespace App\Actions;

use App\Enums\BudgetStatus;
use App\Exceptions\InvalidBudgetTransitionException;
use App\Models\Budget;
use App\Models\User;

class RevertBudgetToSentAction extends ChangesBudgetStatus
{
    /**
     * Returns an accepted or rejected budget to sent, so it can be edited again (RF-49). The
     * answer no longer holds, so its date and reason are cleared; the history keeps them.
     *
     * @throws InvalidBudgetTransitionException
     */
    public function handle(Budget $budget, User $actor): Budget
    {
        return $this->moveTo($budget, BudgetStatus::Sent, ['response_date' => null, 'rejection_reason' => null], $actor);
    }

    protected function allowedFrom(): array
    {
        return [BudgetStatus::Accepted, BudgetStatus::Rejected];
    }
}
