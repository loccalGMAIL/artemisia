<?php

namespace App\Support;

/**
 * Money is handled in whole cents, so sums, products and percentages are exact and every
 * amount ends up with exactly two decimals (RNF-1, RNF-2).
 */
final class Money
{
    public static function toCents(string|int|float|null $amount): int
    {
        if ($amount === null || $amount === '') {
            return 0;
        }

        return (int) round(((float) $amount) * 100);
    }

    public static function fromCents(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $cents = abs($cents);

        return sprintf('%s%d.%02d', $sign, intdiv($cents, 100), $cents % 100);
    }

    /** An amount for screens: "$ 1.234,50". */
    public static function display(string|int|float|null $amount): string
    {
        return '$ '.number_format(self::toCents($amount) / 100, 2, ',', '.');
    }

    /** Integer division rounded to the nearest unit, half up (RNF-2). */
    public static function divideRounded(int $numerator, int $denominator): int
    {
        return intdiv(2 * $numerator + $denominator, 2 * $denominator);
    }
}
