<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class BudgetPdfGenerationException extends RuntimeException
{
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct(__('budgets.pdf.generation_failed'), 0, $previous);
    }
}
