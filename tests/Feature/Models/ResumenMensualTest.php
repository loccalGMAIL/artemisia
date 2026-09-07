<?php

use App\Models\ResumenDetalle;
use App\Models\ResumenMensual;

it('separates base and extra amounts when recalculating', function () {
    $resumen = ResumenMensual::factory()->create();

    ResumenDetalle::factory()->for($resumen)->create([
        'cantidad' => 1,
        'precio_unitario' => 1000,
        'es_extra' => false,
    ]);
    ResumenDetalle::factory()->for($resumen)->create([
        'cantidad' => 1,
        'precio_unitario' => 300,
        'es_extra' => true,
    ]);

    $resumen = $resumen->fresh();

    expect($resumen->monto_base)->toBe('1000.00')
        ->and($resumen->monto_extras)->toBe('300.00')
        ->and($resumen->monto_total)->toBe('1300.00');
});

it('excludes a deleted extra line from the total but keeps the base', function () {
    $resumen = ResumenMensual::factory()->create();

    ResumenDetalle::factory()->for($resumen)->create([
        'cantidad' => 1,
        'precio_unitario' => 1000,
        'es_extra' => false,
    ]);
    $extra = ResumenDetalle::factory()->for($resumen)->create([
        'cantidad' => 1,
        'precio_unitario' => 300,
        'es_extra' => true,
    ]);

    $extra->delete();

    $resumen = $resumen->fresh();

    expect($resumen->monto_base)->toBe('1000.00')
        ->and($resumen->monto_extras)->toBe('0.00')
        ->and($resumen->monto_total)->toBe('1000.00');
});
