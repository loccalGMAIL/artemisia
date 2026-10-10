<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A period already holds the most concepts it admits (RF-46).
 */
class PeriodLimitExceededException extends RuntimeException
{
    public static function concepts(int $max): self
    {
        return new self(__('contracts.validation.concepts_limit', ['max' => $max]));
    }
}
