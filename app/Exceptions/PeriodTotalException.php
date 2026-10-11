<?php

namespace App\Exceptions;

use App\Support\Money;
use RuntimeException;

/**
 * A change would leave the total of a period below zero or below what has already been paid
 * (RF-50, RF-51).
 */
class PeriodTotalException extends RuntimeException
{
    public static function belowZero(): self
    {
        return new self(__('contracts.validation.total_below_zero'));
    }

    public static function belowPaid(string $paid): self
    {
        return new self(__('contracts.validation.total_below_paid', ['paid' => Money::display($paid)]));
    }
}
