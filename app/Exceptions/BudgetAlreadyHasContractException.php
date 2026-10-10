<?php

namespace App\Exceptions;

use App\Models\Contract;
use RuntimeException;

/**
 * A budget gives rise to a single contract; the existing one is shown instead (RF-5).
 */
class BudgetAlreadyHasContractException extends RuntimeException
{
    public function __construct(public readonly Contract $contract)
    {
        parent::__construct(__('contracts.validation.budget_has_contract', ['id' => $contract->id]));
    }
}
