<?php

namespace App\Actions;

use App\Enums\BudgetStatus;
use App\Exceptions\InvalidBudgetTransitionException;
use App\Models\Budget;
use App\Models\User;

class AcceptBudgetAction extends ChangesBudgetStatus
{
    /**
     * Records that the client accepted a sent budget, with the date of the answer (RF-45,
     * RF-46). An expired budget can still be answered (RF-56).
     *
     * @throws InvalidBudgetTransitionException
     */
    public function handle(Budget $budget, User $actor): Budget
    {
        return $this->moveTo($budget, BudgetStatus::Accepted, ['response_date' => today(), 'rejection_reason' => null], $actor);
    }

    protected function allowedFrom(): array
    {
        return [BudgetStatus::Sent];
    }
}
