<?php

namespace App\Exceptions;

use App\Enums\BudgetStatus;
use RuntimeException;

class InvalidBudgetTransitionException extends RuntimeException
{
    public function __construct(BudgetStatus $from, BudgetStatus $to)
    {
        parent::__construct(__('budgets.validation.invalid_transition', [
            'from' => mb_strtolower($from->label()),
            'to' => mb_strtolower($to->label()),
        ]));
    }
}
