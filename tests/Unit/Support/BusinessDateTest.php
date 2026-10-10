<?php

use App\Support\BusinessDate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

afterEach(fn () => Carbon::setTestNow());

it('RF-36, RF-66, RF-74: la zona de negocio es la argentina y la de la aplicación sigue en UTC', function () {
    expect(config('app.business_timezone'))->toBe('America/Argentina/Buenos_Aires')
        ->and(config('app.timezone'))->toBe('UTC');
});

it('RF-36, RF-74: a las 22:30 de Argentina, cuando UTC ya está en el día siguiente, «hoy» y el mes siguen siendo los argentinos', function () {
    Carbon::setTestNow(CarbonImmutable::parse('2026-11-01 01:30:00', 'UTC'));

    expect(BusinessDate::today()->toDateString())->toBe('2026-10-31')
        ->and(BusinessDate::currentMonth()->toDateString())->toBe('2026-10-01');
});

it('RF-36, RF-74: pasada la medianoche argentina, cambian el día y el mes', function () {
    Carbon::setTestNow(CarbonImmutable::parse('2026-11-01 03:30:00', 'UTC'));

    expect(BusinessDate::today()->toDateString())->toBe('2026-11-01')
        ->and(BusinessDate::currentMonth()->toDateString())->toBe('2026-11-01');
});

it('RF-36: «hoy» y el mes actual son fechas sin hora, a las 00:00', function () {
    Carbon::setTestNow(CarbonImmutable::parse('2026-10-15 15:45:10', 'UTC'));

    expect(BusinessDate::today()->format('Y-m-d H:i:s'))->toBe('2026-10-15 00:00:00')
        ->and(BusinessDate::currentMonth()->format('Y-m-d H:i:s'))->toBe('2026-10-01 00:00:00')
        ->and(BusinessDate::today())->toBeInstanceOf(CarbonImmutable::class);
});

it('RF-66: una fecha es futura si es posterior al «hoy» argentino, aunque en UTC ya sea hoy', function () {
    Carbon::setTestNow(CarbonImmutable::parse('2026-11-01 01:30:00', 'UTC'));

    expect(BusinessDate::isFuture('2026-11-01'))->toBeTrue()
        ->and(BusinessDate::isFuture('2026-10-31'))->toBeFalse()
        ->and(BusinessDate::isFuture('2026-10-01'))->toBeFalse();
});

it('RF-74: una fecha ya pasó solo si es anterior al «hoy» argentino', function () {
    Carbon::setTestNow(CarbonImmutable::parse('2026-11-01 01:30:00', 'UTC'));

    expect(BusinessDate::isPast('2026-10-30'))->toBeTrue()
        ->and(BusinessDate::isPast('2026-10-31'))->toBeFalse()
        ->and(BusinessDate::isPast('2026-11-01'))->toBeFalse();
});
