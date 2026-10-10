<?php

namespace App\Exceptions;

use RuntimeException;

class InvalidPieceAssigneeException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(__('pieces.validation.assignee_invalid'));
    }
}
