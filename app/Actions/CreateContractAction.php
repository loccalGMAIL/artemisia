<?php

namespace App\Actions;

use App\Enums\BudgetModality;
use App\Enums\BudgetStatus;
use App\Enums\ContractHistoryField;
use App\Enums\ContractStatus;
use App\Exceptions\BudgetAlreadyHasContractException;
use App\Exceptions\BudgetNotEligibleForContractException;
use App\Models\Budget;
use App\Models\Contract;
use App\Models\ContractHistory;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class CreateContractAction
{
    /**
     * Creates the contract of an accepted monthly budget with a total above zero, copying the
     * client and the monthly amount, and leaves it active with its first history entry
     * (RF-1 to RF-6, RF-20, RF-33). Later changes to the budget never reach the contract (RF-18).
     *
     * @param  array{start_date: string, due_day: int|string, term_months: int|string|null}  $data
     *
     * @throws BudgetNotEligibleForContractException
     * @throws BudgetAlreadyHasContractException
     */
    public function handle(Budget $budget, array $data, User $author): Contract
    {
        $this->assertEligible($budget);

        return DB::transaction(function () use ($budget, $data, $author): Contract {
            $contract = Contract::query()->create([
                'budget_id' => $budget->id,
                'client_id' => $budget->client_id,
                'start_date' => $data['start_date'],
                'term_months' => $data['term_months'] ?? null,
                'due_day' => $data['due_day'],
                'monthly_amount' => $budget->total,
                'status' => ContractStatus::Active,
                'created_by' => $author->id,
            ]);

            ContractHistory::query()->create([
                'contract_id' => $contract->id,
                'field' => ContractHistoryField::Created,
                'old_value' => null,
                'new_value' => $contract->creationSnapshot(),
                'author_id' => $author->id,
            ]);

            return $contract;
        });
    }

    /**
     * @throws BudgetNotEligibleForContractException
     * @throws BudgetAlreadyHasContractException
     */
    private function assertEligible(Budget $budget): void
    {
        if ($budget->status !== BudgetStatus::Accepted) {
            throw new BudgetNotEligibleForContractException(BudgetNotEligibleForContractException::NOT_ACCEPTED);
        }

        if ($budget->modality !== BudgetModality::Monthly) {
            throw new BudgetNotEligibleForContractException(BudgetNotEligibleForContractException::NOT_MONTHLY);
        }

        if (Money::toCents($budget->total) <= 0) {
            throw new BudgetNotEligibleForContractException(BudgetNotEligibleForContractException::ZERO_AMOUNT);
        }

        $existing = Contract::withTrashed()->where('budget_id', $budget->id)->first();

        if ($existing !== null) {
            throw new BudgetAlreadyHasContractException($existing);
        }
    }
}
