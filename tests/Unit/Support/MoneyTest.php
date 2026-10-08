<?php

use App\Support\Money;

it('RNF-1: pasa un importe decimal a centavos enteros sin errores de coma flotante', function (string|int|float|null $amount, int $cents) {
    expect(Money::toCents($amount))->toBe($cents);
})->with([
    'dos decimales' => ['12.50', 1250],
    'un decimal' => ['0.1', 10],
    'entero' => ['100', 10000],
    'número entero' => [100, 10000],
    'con flotante' => [19.99, 1999],
    'trampa de flotantes' => ['0.29', 29],
    'cero' => ['0.00', 0],
    'nulo' => [null, 0],
]);

it('RNF-1: expresa todo importe con exactamente 2 decimales', function (int $cents, string $expected) {
    expect(Money::fromCents($cents))->toBe($expected);
})->with([
    'cero' => [0, '0.00'],
    'centavos' => [5, '0.05'],
    'exacto' => [1250, '12.50'],
    'grande' => [123456789, '1234567.89'],
]);

it('RNF-2: redondea al múltiplo de 0,01 más cercano, la mitad hacia arriba', function (int $numerator, int $denominator, int $expected) {
    expect(Money::divideRounded($numerator, $denominator))->toBe($expected);
})->with([
    'justo' => [100, 4, 25],
    'por debajo de la mitad' => [14, 10, 1],
    'la mitad exacta sube' => [15, 10, 2],
    'por encima de la mitad' => [16, 10, 2],
    'cero' => [0, 7, 0],
]);
