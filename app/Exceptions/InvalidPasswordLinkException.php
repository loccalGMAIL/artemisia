<?php

namespace App\Exceptions;

use RuntimeException;

class InvalidPasswordLinkException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(__('auth.invalid_password_link'));
    }
}
