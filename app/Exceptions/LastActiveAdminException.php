<?php

namespace App\Exceptions;

use RuntimeException;

class LastActiveAdminException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(__('auth.last_active_admin'));
    }
}
