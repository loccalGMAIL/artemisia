<?php

namespace App\Exceptions;

use RuntimeException;

class BudgetNotEditableException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(__('budgets.validation.budget_not_editable'));
    }
}
