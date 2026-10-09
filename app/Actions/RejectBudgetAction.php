<?php

namespace App\Actions;

use App\Enums\BudgetStatus;
use App\Exceptions\InvalidBudgetTransitionException;
use App\Models\Budget;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RejectBudgetAction extends ChangesBudgetStatus
{
    /**
     * Records that the client rejected a sent budget, with the date of the answer and an
     * optional reason (RF-45, RF-46, RF-47).
     *
     * @throws InvalidBudgetTransitionException
     * @throws ValidationException
     */
    public function handle(Budget $budget, ?string $reason, User $actor): Budget
    {
        $validated = Validator::make(['rejection_reason' => $reason], [
            'rejection_reason' => ['nullable', 'string', 'max:255'],
        ])->validate();

        $reason = isset($validated['rejection_reason']) ? trim($validated['rejection_reason']) : null;

        return $this->moveTo($budget, BudgetStatus::Rejected, [
            'response_date' => today(),
            'rejection_reason' => $reason === '' ? null : $reason,
        ], $actor);
    }

    protected function allowedFrom(): array
    {
        return [BudgetStatus::Sent];
    }
}
