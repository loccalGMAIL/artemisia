<?php

namespace App\Actions;

use App\Enums\ContractHistoryField;
use App\Enums\ContractStatus;
use App\Exceptions\InvalidContractTransitionException;
use App\Models\Contract;
use App\Models\ContractHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Common flow of changing a term of an active contract, its monthly amount or its due day
 * (RF-30, RF-31). The change reaches only the periods generated from then on (RF-32), so no
 * period is touched here, and it is recorded with the previous and the new value (RF-33).
 * Saving the value it already has records nothing.
 */
abstract class ChangesContractTerms
{
    /**
     * @throws InvalidContractTransitionException
     */
    protected function change(Contract $contract, string $column, int|string $value, ContractHistoryField $field, User $actor): Contract
    {
        DB::transaction(function () use ($contract, $column, $value, $field, $actor): void {
            $locked = Contract::query()->whereKey($contract->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== ContractStatus::Active) {
                throw InvalidContractTransitionException::notActive($locked->status);
            }

            $before = $locked->getAttribute($column);

            if ($before === $value) {
                return;
            }

            $locked->forceFill([$column => $value])->save();

            ContractHistory::query()->create([
                'contract_id' => $locked->id,
                'field' => $field,
                'old_value' => [$column => $before],
                'new_value' => [$column => $locked->getAttribute($column)],
                'author_id' => $actor->id,
            ]);
        });

        return $contract->refresh();
    }
}
