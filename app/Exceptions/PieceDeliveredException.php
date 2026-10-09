<?php

namespace App\Exceptions;

use RuntimeException;

class PieceDeliveredException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(__('pieces.validation.piece_delivered'));
    }
}
