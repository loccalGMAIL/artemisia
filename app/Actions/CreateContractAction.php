<?php

namespace App\Actions;

use App\Enums\BudgetModality;
use App\Enums\BudgetStatus;
use App\Enums\ContractHistoryField;
use App\Enums\ContractStatus;
use App\Exceptions\BudgetAlreadyHasContractException;
use App\Exceptions\BudgetNotEligibleForContractException;
use App\Exceptions\ContractTermElapsedException;
use App\Models\Budget;
use App\Models\Contract;
use App\Models\ContractHistory;
use App\Models\User;
use App\Support\BusinessDate;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CreateContractAction
{
    /**
     * Creates the contract of an accepted monthly budget with a total above zero, copying the
     * client and the monthly amount, and leaves it active with its first history entry
     * (RF-1 to RF-6, RF-20, RF-33). Later changes to the budget never reach the contract (RF-18).
     *
     * The term is either `indefinite` or a number of `term_months` (RF-7, RF-8).
     *
     * @param  array{start_date?: string|null, due_day?: int|string|null, term_months?: int|string|null, indefinite?: bool}  $data
     *
     * @throws BudgetNotEligibleForContractException
     * @throws BudgetAlreadyHasContractException
     * @throws ContractTermElapsedException
     * @throws ValidationException
     */
    public function handle(Budget $budget, array $data, User $author): Contract
    {
        $this->assertEligible($budget);

        $values = $this->validate($data);

        return DB::transaction(function () use ($budget, $values, $author): Contract {
            $contract = Contract::query()->create([
                'budget_id' => $budget->id,
                'client_id' => $budget->client_id,
                'start_date' => $values['start_date'],
                'term_months' => $values['term_months'],
                'term_end_month' => $values['term_end_month'],
                'due_day' => $values['due_day'],
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

    /**
     * Validates the contract data and works out the last month of a fixed term (plan D-12).
     *
     * @param  array<string, mixed>  $data
     * @return array{start_date: string, due_day: int, term_months: int|null, term_end_month: string|null}
     *
     * @throws ValidationException
     * @throws ContractTermElapsedException
     */
    private function validate(array $data): array
    {
        $indefinite = filter_var($data['indefinite'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $validated = Validator::make($data, [
            'start_date' => ['required', 'date'],
            'due_day' => ['required', 'integer', 'between:1,28'],
            'term_months' => $indefinite ? ['nullable'] : ['required', 'integer', 'min:1'],
        ], [
            'start_date.required' => __('contracts.validation.start_date_required'),
            'start_date.date' => __('contracts.validation.start_date_required'),
            'due_day.required' => __('contracts.validation.due_day_range'),
            'due_day.integer' => __('contracts.validation.due_day_range'),
            'due_day.between' => __('contracts.validation.due_day_range'),
            'term_months.required' => __('contracts.validation.term_months_invalid'),
            'term_months.integer' => __('contracts.validation.term_months_invalid'),
            'term_months.min' => __('contracts.validation.term_months_invalid'),
        ])->validate();

        $months = $indefinite ? null : (int) $validated['term_months'];
        $lastMonth = null;

        if ($months !== null) {
            $lastMonth = CarbonImmutable::parse($validated['start_date'])->startOfMonth()->addMonths($months - 1);

            if ($lastMonth->addMonth()->lte(BusinessDate::currentMonth())) {
                throw new ContractTermElapsedException;
            }
        }

        return [
            'start_date' => CarbonImmutable::parse($validated['start_date'])->toDateString(),
            'due_day' => (int) $validated['due_day'],
            'term_months' => $months,
            'term_end_month' => $lastMonth?->toDateString(),
        ];
    }
}
