<?php

namespace App\Exceptions;

use RuntimeException;

class CannotDeactivateOwnAccountException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(__('auth.cannot_deactivate_own_account'));
    }
}
