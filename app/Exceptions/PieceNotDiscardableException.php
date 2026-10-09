<?php

namespace App\Exceptions;

use RuntimeException;

class PieceNotDiscardableException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(__('pieces.validation.not_discardable'));
    }
}
