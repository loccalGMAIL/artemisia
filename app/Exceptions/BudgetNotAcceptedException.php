<?php

namespace App\Exceptions;

use RuntimeException;

class BudgetNotAcceptedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(__('pieces.validation.budget_not_accepted'));
    }
}
