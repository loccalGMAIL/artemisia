<?php

namespace App\Actions;

use App\Enums\ContractHistoryField;
use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Models\ContractHistory;
use App\Support\BusinessDate;
use Illuminate\Support\Facades\DB;

class FinishExpiredContractsAction
{
    /**
     * Finishes the active fixed-term contracts whose last month of the term is already behind
     * the current month, that is, once the month after it starts (RF-29). There is no user
     * behind it, so the history entry has no author and reads as the system (RF-33, plan D-7).
     * Running it again finds nothing left to finish.
     *
     * @return int how many contracts were finished
     */
    public function handle(): int
    {
        $finished = 0;

        Contract::query()
            ->where('status', ContractStatus::Active)
            ->where('term_end_month', '<', BusinessDate::currentMonth()->toDateString())
            ->orderBy('id')
            ->each(function (Contract $candidate) use (&$finished): void {
                $finished += DB::transaction(function () use ($candidate): int {
                    $contract = Contract::query()->whereKey($candidate->id)->lockForUpdate()->firstOrFail();

                    if ($contract->status !== ContractStatus::Active) {
                        return 0;
                    }

                    $before = $contract->statusSnapshot();

                    $contract->forceFill(['status' => ContractStatus::Finished])->save();

                    ContractHistory::query()->create([
                        'contract_id' => $contract->id,
                        'field' => ContractHistoryField::StatusChanged,
                        'old_value' => $before,
                        'new_value' => $contract->statusSnapshot(),
                        'author_id' => null,
                    ]);

                    return 1;
                });
            });

        return $finished;
    }
}
