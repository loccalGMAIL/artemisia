<?php

namespace App\Actions;

use App\Enums\ContractStatus;
use App\Exceptions\ContractTermElapsedException;
use App\Exceptions\InvalidContractTransitionException;
use App\Models\Contract;
use App\Models\User;
use App\Support\BusinessDate;

class ReactivateContractAction extends ChangesContractStatus
{
    public function __construct(private readonly GenerateContractPeriodsAction $generatePeriods) {}

    /**
     * Reactivates a cancelled contract (RF-25) and creates the periods it is missing up to the
     * current month, never beyond its term (RF-26). A finished contract is not reactivated
     * (RF-28), and neither is one whose fixed term has already gone by (RF-27).
     *
     * @throws InvalidContractTransitionException
     * @throws ContractTermElapsedException
     */
    public function handle(Contract $contract, User $actor): Contract
    {
        return $this->moveTo($contract, ContractStatus::Active, $actor);
    }

    protected function allowedFrom(): ContractStatus
    {
        return ContractStatus::Cancelled;
    }

    protected function beforeMove(Contract $contract): void
    {
        if ($contract->term_end_month !== null && $contract->term_end_month->addMonth()->lte(BusinessDate::currentMonth())) {
            throw new ContractTermElapsedException;
        }
    }

    protected function afterMove(Contract $contract): void
    {
        $this->generatePeriods->handle($contract);
    }
}
