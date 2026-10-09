<?php

namespace App\Exceptions;

use RuntimeException;

class BudgetNotDiscardableException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(__('budgets.validation.not_discardable'));
    }
}
