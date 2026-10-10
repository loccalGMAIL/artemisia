<?php

namespace App\Actions;

use App\Enums\PeriodHistoryField;
use App\Exceptions\PeriodTotalException;
use App\Models\ContractPeriod;
use App\Models\PeriodHistory;
use App\Models\User;
use App\Support\Money;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Common flow of every Action that changes a period: one transaction that locks the period
 * row before anything is validated, so two people working on the same period cannot step on
 * each other (plan D-8), recalculates the totals and refuses a result below zero or below what
 * has been paid (RF-50, RF-51). Whatever the change throws rolls everything back.
 */
abstract class ChangesPeriod
{
    public function __construct(protected readonly RecalculatePeriodTotalsAction $recalculate) {}

    /**
     * @template TResult
     *
     * @param  Closure(ContractPeriod): TResult  $change  receives the period as it is in the database now
     * @return TResult
     */
    protected function changing(ContractPeriod $period, Closure $change): mixed
    {
        $result = DB::transaction(function () use ($period, $change): mixed {
            $locked = ContractPeriod::query()->whereKey($period->id)->lockForUpdate()->firstOrFail();

            $result = $change($locked);

            $this->recalculate->handle($locked);

            $this->assertTotalIsAllowed($locked);

            return $result;
        });

        $period->refresh();

        return $result;
    }

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    protected function record(ContractPeriod $period, PeriodHistoryField $field, ?array $old, ?array $new, ?User $actor): PeriodHistory
    {
        return PeriodHistory::query()->create([
            'contract_period_id' => $period->id,
            'field' => $field,
            'old_value' => $old,
            'new_value' => $new,
            'author_id' => $actor?->id,
        ]);
    }

    private function assertTotalIsAllowed(ContractPeriod $period): void
    {
        $total = Money::toCents($period->total);

        if ($total < 0) {
            throw PeriodTotalException::belowZero();
        }

        if ($total < Money::toCents($period->paid_amount)) {
            throw PeriodTotalException::belowPaid($period->paid_amount);
        }
    }
}
