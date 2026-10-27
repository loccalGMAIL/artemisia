<?php

namespace App\Exceptions;

use RuntimeException;

class CannotChangeOwnEmailException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(__('auth.cannot_change_own_email'));
    }
}
