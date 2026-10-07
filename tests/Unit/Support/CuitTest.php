<?php

use App\Support\Cuit;

it('RNF-3: calcula el dígito verificador con el algoritmo módulo 11', function (string $firstTen, int $digit) {
    expect(Cuit::checkDigit($firstTen))->toBe($digit);
})->with([
    '20-12345678' => ['2012345678', 6],
    '30-71234567' => ['3071234567', 1],
    'resto cero' => ['2012345605', 0],
]);

it('RNF-3: no existe dígito verificador cuando el resto da 1', function () {
    expect(Cuit::checkDigit('2012345600'))->toBeNull();
});

it('RNF-3: valida CUITs de 11 dígitos con dígito verificador correcto', function (string $cuit, bool $valid) {
    expect(Cuit::isValid($cuit))->toBe($valid);
})->with([
    'válido' => ['20123456786', true],
    'otro válido' => ['30712345671', true],
    'dígito incorrecto' => ['20123456787', false],
    'muy corto' => ['2012345678', false],
    'muy largo' => ['201234567860', false],
    'con letras' => ['2012345678a', false],
    'vacío' => ['', false],
]);
