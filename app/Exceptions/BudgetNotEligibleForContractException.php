<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * The budget cannot originate a contract; the reason says why (RF-2, RF-3, RF-4).
 */
class BudgetNotEligibleForContractException extends RuntimeException
{
    public const NOT_ACCEPTED = 'budget_not_accepted';

    public const NOT_MONTHLY = 'budget_not_monthly';

    public const ZERO_AMOUNT = 'budget_zero_amount';

    public function __construct(public readonly string $reason)
    {
        parent::__construct(__("contracts.validation.{$reason}"));
    }
}
