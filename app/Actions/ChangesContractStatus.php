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
 * Common flow of a change of state of a contract: it is decided on the contract as it is when
 * confirmed, with its row locked, so two people cannot both cancel it (plan D-8), and it is
 * recorded with the previous and the new state (RF-33).
 */
abstract class ChangesContractStatus
{
    /** The only state this Action starts from. */
    abstract protected function allowedFrom(): ContractStatus;

    /**
     * @throws InvalidContractTransitionException
     */
    protected function moveTo(Contract $contract, ContractStatus $next, User $actor): Contract
    {
        DB::transaction(function () use ($contract, $next, $actor): void {
            $locked = Contract::query()->whereKey($contract->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== $this->allowedFrom()) {
                throw InvalidContractTransitionException::transition($locked->status, $next);
            }

            $this->beforeMove($locked);

            $before = $locked->statusSnapshot();

            $locked->forceFill(['status' => $next])->save();

            ContractHistory::query()->create([
                'contract_id' => $locked->id,
                'field' => ContractHistoryField::StatusChanged,
                'old_value' => $before,
                'new_value' => $locked->statusSnapshot(),
                'author_id' => $actor->id,
            ]);

            $this->afterMove($locked);
        });

        return $contract->refresh();
    }

    /** Extra conditions of the change, checked before anything is written. */
    protected function beforeMove(Contract $contract): void {}

    /** Whatever must happen in the same transaction once the state changed. */
    protected function afterMove(Contract $contract): void {}
}
