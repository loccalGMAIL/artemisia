<?php

namespace App\Support;

/**
 * CUIT validation with the standard modulo 11 check digit (RNF-3).
 */
final class Cuit
{
    private const WEIGHTS = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];

    /**
     * The check digit for the first ten digits, or null when none exists (remainder 1)
     * or the input is not exactly ten digits.
     */
    public static function checkDigit(string $firstTen): ?int
    {
        if (! preg_match('/^\d{10}$/', $firstTen)) {
            return null;
        }

        $sum = 0;

        foreach (str_split($firstTen) as $position => $digit) {
            $sum += (int) $digit * self::WEIGHTS[$position];
        }

        $remainder = $sum % 11;

        return match ($remainder) {
            0 => 0,
            1 => null,
            default => 11 - $remainder,
        };
    }

    public static function isValid(string $cuit): bool
    {
        if (! preg_match('/^\d{11}$/', $cuit)) {
            return false;
        }

        return self::checkDigit(substr($cuit, 0, 10)) === (int) $cuit[10];
    }
}
