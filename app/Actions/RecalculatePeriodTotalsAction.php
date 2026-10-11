<?php

namespace App\Actions;

use App\Enums\ConceptType;
use App\Models\ContractPeriod;
use App\Models\Payment;
use App\Support\Money;

class RecalculatePeriodTotalsAction
{
    /**
     * Recomputes and stores the total of a period, its fee plus its extras minus its discounts
     * (RF-49), and what has been paid, the sum of its payments that were not voided (RF-68).
     * All the arithmetic is done in whole cents (RNF-2).
     */
    public function handle(ContractPeriod $period): ContractPeriod
    {
        $total = Money::toCents($period->fee);

        foreach ($period->concepts()->get(['type', 'amount']) as $concept) {
            $cents = Money::toCents($concept->amount);

            $total += $concept->type === ConceptType::Extra ? $cents : -$cents;
        }

        $paid = $period->payments()->valid()->get(['amount'])
            ->sum(fn (Payment $payment): int => Money::toCents($payment->amount));

        $period->forceFill([
            'total' => Money::fromCents($total),
            'paid_amount' => Money::fromCents($paid),
        ])->save();

        return $period;
    }
}
