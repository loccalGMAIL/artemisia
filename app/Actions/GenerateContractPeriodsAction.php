<?php

namespace App\Actions;

use App\Enums\ContractStatus;
use App\Enums\PeriodHistoryField;
use App\Models\Contract;
use App\Models\ContractPeriod;
use App\Support\BusinessDate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GenerateContractPeriodsAction
{
    /**
     * Creates the periods a contract is missing, from its start month to the current month and
     * never beyond its fixed term (RF-36, RF-37, RF-39). A month that did not run is made up
     * on the next run, a future start generates nothing, and there is at most one period per
     * month (RF-38). Each period takes the contract's amount and due day as they are now, even
     * if that date has already gone by (RF-40), and starts its history with the system as the
     * author (RF-41).
     *
     * Only an active contract generates periods (RF-22); the state of the client does not
     * matter (RF-35).
     *
     * @return Collection<int, ContractPeriod> the periods that were created, oldest first
     */
    public function handle(Contract $contract): Collection
    {
        if ($contract->status !== ContractStatus::Active) {
            return new Collection;
        }

        return DB::transaction(function () use ($contract): Collection {
            // Two runs on the same contract cannot create the same month twice.
            $locked = Contract::query()->whereKey($contract->id)->lockForUpdate()->firstOrFail();

            $months = $this->missingMonths($locked);

            if ($months === []) {
                return new Collection;
            }

            $this->insertPeriods($locked, $months);

            return ContractPeriod::query()
                ->where('contract_id', $locked->id)
                ->whereIn('month', $months)
                ->orderBy('month')
                ->get();
        });
    }

    /**
     * @return list<string> the first day of every month without a period, as dates
     */
    private function missingMonths(Contract $contract): array
    {
        $first = CarbonImmutable::parse($contract->start_date)->startOfMonth();
        $last = BusinessDate::currentMonth();

        if ($contract->term_end_month !== null && $contract->term_end_month->lt($last)) {
            $last = CarbonImmutable::parse($contract->term_end_month);
        }

        if ($first->gt($last)) {
            return [];
        }

        $existing = ContractPeriod::query()
            ->where('contract_id', $contract->id)
            ->get(['month'])
            ->map(fn (ContractPeriod $period): string => $period->month->toDateString())
            ->all();

        $missing = [];

        for ($month = $first; $month->lte($last); $month = $month->addMonth()) {
            if (! in_array($month->toDateString(), $existing, true)) {
                $missing[] = $month->toDateString();
            }
        }

        return $missing;
    }

    /**
     * @param  list<string>  $months
     */
    private function insertPeriods(Contract $contract, array $months): void
    {
        $now = now();

        ContractPeriod::query()->insert(array_map(fn (string $month): array => [
            'contract_id' => $contract->id,
            'client_id' => $contract->client_id,
            'month' => $month,
            'due_date' => CarbonImmutable::parse($month)->addDays($contract->due_day - 1)->toDateString(),
            'fee' => $contract->monthly_amount,
            'total' => $contract->monthly_amount,
            'paid_amount' => '0.00',
            'created_at' => $now,
            'updated_at' => $now,
        ], $months));

        $created = ContractPeriod::query()
            ->where('contract_id', $contract->id)
            ->whereIn('month', $months)
            ->get(['id', 'month', 'fee', 'due_date']);

        DB::table('period_histories')->insert($created->map(fn (ContractPeriod $period): array => [
            'contract_period_id' => $period->id,
            'field' => PeriodHistoryField::Generated->value,
            'old_value' => null,
            'new_value' => json_encode([
                'month' => $period->month->toDateString(),
                'fee' => $period->fee,
                'due_date' => $period->due_date->toDateString(),
            ]),
            'author_id' => null,
            'created_at' => $now,
        ])->all());
    }
}
