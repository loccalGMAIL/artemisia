<?php

namespace App\Actions;

use App\Enums\ContractHistoryField;
use App\Exceptions\InvalidContractTransitionException;
use App\Models\Contract;
use App\Models\User;
use App\Support\Money;
use Illuminate\Validation\ValidationException;

class UpdateContractAmountAction extends ChangesContractTerms
{
    /**
     * Changes the monthly amount of an active contract (RF-30). It has to be above zero and have
     * at most 2 decimals (RF-10); it is not rounded, a longer value is refused.
     *
     * @throws ValidationException
     * @throws InvalidContractTransitionException
     */
    public function handle(Contract $contract, mixed $amount, User $actor): Contract
    {
        $text = is_int($amount) || is_float($amount) || is_string($amount) ? trim((string) $amount) : '';

        if (preg_match('/^\d{1,10}(\.\d{1,2})?$/', $text) !== 1 || Money::toCents($text) < 1) {
            throw ValidationException::withMessages(['monthly_amount' => __('contracts.validation.monthly_amount_invalid')]);
        }

        return $this->change($contract, 'monthly_amount', Money::fromCents(Money::toCents($text)), ContractHistoryField::AmountChanged, $actor);
    }
}
