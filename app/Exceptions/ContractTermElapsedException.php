<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * The fixed term of a contract has already gone by, so it cannot be created or reactivated
 * (RF-12, RF-27).
 */
class ContractTermElapsedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(__('contracts.validation.term_elapsed'));
    }
}
