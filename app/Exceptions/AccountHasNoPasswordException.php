<?php

namespace App\Exceptions;

use RuntimeException;

class AccountHasNoPasswordException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(__('auth.account_has_no_password'));
    }
}
