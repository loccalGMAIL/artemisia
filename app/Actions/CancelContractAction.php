<?php

namespace App\Actions;

use App\Enums\ContractStatus;
use App\Exceptions\InvalidContractTransitionException;
use App\Models\Contract;
use App\Models\User;

class CancelContractAction extends ChangesContractStatus
{
    /**
     * Cancels an active contract (RF-21). It stops generating periods (RF-22) and keeps the ones
     * it already has, with their concepts, balance and payments (RF-23): nothing else is touched.
     *
     * @throws InvalidContractTransitionException
     */
    public function handle(Contract $contract, User $actor): Contract
    {
        return $this->moveTo($contract, ContractStatus::Cancelled, $actor);
    }

    protected function allowedFrom(): ContractStatus
    {
        return ContractStatus::Active;
    }
}
