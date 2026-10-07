<?php

namespace App\Exceptions;

use App\Models\User;
use RuntimeException;

class DuplicateAccountEmailException extends RuntimeException
{
    public function __construct(public readonly User $existingAccount)
    {
        parent::__construct(__('auth.email_conflict', [
            'email' => $existingAccount->email,
            'name' => $existingAccount->name,
        ]));
    }
}
