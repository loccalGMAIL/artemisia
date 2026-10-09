<?php

namespace App\Actions;

use App\Enums\BudgetStatus;
use App\Exceptions\InvalidBudgetTransitionException;
use App\Models\Budget;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class SendBudgetAction extends ChangesBudgetStatus
{
    /**
     * Marks a draft as sent; a budget without items cannot be sent (RF-43, RF-44).
     *
     * @throws InvalidBudgetTransitionException
     * @throws ValidationException
     */
    public function handle(Budget $budget, User $actor): Budget
    {
        $this->assertMoveAllowed($budget, BudgetStatus::Sent);

        if (! $budget->items()->exists()) {
            throw ValidationException::withMessages(['items' => __('budgets.validation.cannot_send_without_items')]);
        }

        return $this->moveTo($budget, BudgetStatus::Sent, [], $actor);
    }

    protected function allowedFrom(): array
    {
        return [BudgetStatus::Draft];
    }
}
