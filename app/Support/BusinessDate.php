<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Days and months of the business, counted in the agency's own zone (spec 005, plan D-4).
 *
 * The application runs in UTC, which is already on the next day between 21:00 and 24:00 in
 * Argentina. Every "today", "current month" and "future date" of the contracts module goes
 * through here, and the dates are stored as plain dates without a time.
 */
final class BusinessDate
{
    /** Today at 00:00 in the business zone. */
    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::now(self::zone())->startOfDay();
    }

    /** The first day of the current month, at 00:00 in the business zone. */
    public static function currentMonth(): CarbonImmutable
    {
        return self::today()->startOfMonth();
    }

    /** Whether the date comes after today (RF-66). */
    public static function isFuture(CarbonInterface|string $date): bool
    {
        return self::dayOf($date)->gt(self::today());
    }

    /** Whether the date is before today (RF-74). */
    public static function isPast(CarbonInterface|string $date): bool
    {
        return self::dayOf($date)->lt(self::today());
    }

    private static function dayOf(CarbonInterface|string $date): CarbonImmutable
    {
        $day = $date instanceof CarbonInterface
            ? $date->format('Y-m-d')
            : CarbonImmutable::parse($date)->format('Y-m-d');

        return CarbonImmutable::createFromFormat('Y-m-d', $day, self::zone())->startOfDay();
    }

    private static function zone(): string
    {
        return (string) config('app.business_timezone');
    }
}
