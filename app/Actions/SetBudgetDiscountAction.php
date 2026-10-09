<?php

namespace App\Actions;

use App\Enums\BudgetDiscountType;
use App\Enums\BudgetHistoryField;
use App\Exceptions\BudgetNotEditableException;
use App\Models\Budget;
use App\Models\BudgetHistory;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SetBudgetDiscountAction
{
    public function __construct(private readonly RecalculateBudgetTotalsAction $recalculate) {}

    /**
     * Loads, replaces or removes the single discount of a draft or sent budget (RF-36, RF-37).
     * A null type removes it. A percentage goes from 0 to 100 (RNF-3) and a fixed amount may
     * not leave the total below zero (RF-39). Every change is recorded (RF-40).
     *
     * @throws BudgetNotEditableException
     * @throws ValidationException
     */
    public function handle(Budget $budget, ?string $type, mixed $value, User $actor): Budget
    {
        $budget->assertEditable();

        $discount = $type === null || $type === '' ? null : $this->validated($budget, $type, $value);

        $before = $budget->discountSnapshot();

        return DB::transaction(function () use ($budget, $discount, $before, $actor): Budget {
            $budget->discount_type = $discount['type'] ?? null;
            $budget->discount_value = $discount['value'] ?? null;

            $this->recalculate->handle($budget);

            $after = $budget->discountSnapshot();

            if ($after !== $before) {
                BudgetHistory::query()->create([
                    'budget_id' => $budget->id,
                    'field' => BudgetHistoryField::DiscountChanged,
                    'old_value' => $before,
                    'new_value' => $after,
                    'author_id' => $actor->id,
                ]);
            }

            return $budget;
        });
    }

    /**
     * @return array{type: BudgetDiscountType, value: string}
     *
     * @throws ValidationException
     */
    private function validated(Budget $budget, string $type, mixed $value): array
    {
        $validated = Validator::make(
            ['discount_type' => $type, 'discount_value' => $value],
            [
                'discount_type' => ['required', Rule::enum(BudgetDiscountType::class)],
                'discount_value' => ['required', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
            ],
        )->validate();

        $discountType = BudgetDiscountType::from($validated['discount_type']);
        $cents = Money::toCents($validated['discount_value']);

        if ($discountType === BudgetDiscountType::Percentage && $cents > 10000) {
            throw ValidationException::withMessages(['discount_value' => __('budgets.validation.percentage_range')]);
        }

        if ($discountType === BudgetDiscountType::Fixed && $cents > Money::toCents($budget->subtotal)) {
            throw ValidationException::withMessages(['discount_value' => __('budgets.validation.discount_exceeds_total')]);
        }

        return ['type' => $discountType, 'value' => Money::fromCents($cents)];
    }
}
