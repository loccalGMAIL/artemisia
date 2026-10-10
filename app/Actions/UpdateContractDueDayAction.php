<?php

namespace App\Actions;

use App\Enums\ContractHistoryField;
use App\Exceptions\InvalidContractTransitionException;
use App\Models\Contract;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class UpdateContractDueDayAction extends ChangesContractTerms
{
    /**
     * Changes the due day of an active contract (RF-31): a whole number from 1 to 28.
     *
     * @throws ValidationException
     * @throws InvalidContractTransitionException
     */
    public function handle(Contract $contract, mixed $day, User $actor): Contract
    {
        $text = match (true) {
            is_int($day), is_string($day) => trim((string) $day),
            is_float($day) && floor($day) === $day => (string) (int) $day,
            default => '',
        };

        if (preg_match('/^\d{1,2}$/', $text) !== 1 || (int) $text < 1 || (int) $text > 28) {
            throw ValidationException::withMessages(['due_day' => __('contracts.validation.due_day_range')]);
        }

        return $this->change($contract, 'due_day', (int) $text, ContractHistoryField::DueDayChanged, $actor);
    }
}
