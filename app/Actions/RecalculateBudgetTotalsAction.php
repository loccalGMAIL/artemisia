<?php

namespace App\Actions;

use App\Enums\BudgetDiscountType;
use App\Models\Budget;
use App\Support\Money;

class RecalculateBudgetTotalsAction
{
    /**
     * Recomputes and stores subtotal, discount and total (RF-35, RF-38). All the arithmetic is
     * done in whole cents, and a percentage discount is rounded to the nearest cent (RNF-2).
     * A fixed discount larger than the subtotal, which can happen after removing items, is
     * capped so the total never goes below zero.
     */
    public function handle(Budget $budget): Budget
    {
        $subtotal = $budget->items()->get()->sum(fn ($item): int => $item->amountInCents());

        $discount = match ($budget->discount_type) {
            BudgetDiscountType::Percentage => Money::divideRounded($subtotal * Money::toCents($budget->discount_value), 10000),
            BudgetDiscountType::Fixed => Money::toCents($budget->discount_value),
            null => 0,
        };

        $discount = min($discount, $subtotal);

        $budget->forceFill([
            'subtotal' => Money::fromCents($subtotal),
            'discount_amount' => Money::fromCents($discount),
            'total' => Money::fromCents($subtotal - $discount),
        ])->save();

        return $budget;
    }
}
