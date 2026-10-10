<?php

namespace App\Exceptions;

use App\Enums\PieceStatus;
use RuntimeException;

class InvalidPieceTransitionException extends RuntimeException
{
    public function __construct(public readonly PieceStatus $from, public readonly PieceStatus $to)
    {
        parent::__construct(__('pieces.validation.invalid_transition', [
            'from' => $from->label(),
            'to' => $to->label(),
        ]));
    }
}
