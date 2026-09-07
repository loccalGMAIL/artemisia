<?php

use App\Models\Contrato;
use App\Models\DetallePresupuesto;
use App\Models\Presupuesto;
use App\Models\ResumenDetalle;

it('lists the three monthly periods covered by the contract', function () {
    $contrato = Contrato::factory()->create([
        'fecha_inicio' => '2026-04-15',
        'fecha_fin' => '2026-07-15',
    ]);

    expect($contrato->periodos())->toBe(['2026-04', '2026-05', '2026-06']);
});

it('creates a monthly summary per period preloaded with the budget lines', function () {
    $presupuesto = Presupuesto::factory()->create();
    DetallePresupuesto::factory()->for($presupuesto)->create(['cantidad' => 1, 'precio_unitario' => 1000]);
    DetallePresupuesto::factory()->for($presupuesto)->create(['cantidad' => 2, 'precio_unitario' => 500]);

    $contrato = Contrato::factory()->for($presupuesto)->create([
        'fecha_inicio' => '2026-04-01',
        'fecha_fin' => '2026-07-01',
    ]);

    $contrato->generarResumenesMensuales();

    $resumenes = $contrato->resumenesMensuales()->orderBy('periodo')->get();

    expect($resumenes->pluck('periodo')->all())->toBe(['2026-04', '2026-05', '2026-06']);

    $resumenes->each(function ($resumen) {
        expect($resumen->detalles)->toHaveCount(2)
            ->and($resumen->detalles->every(fn (ResumenDetalle $d) => $d->es_extra === false))->toBeTrue()
            ->and($resumen->monto_base)->toBe('2000.00');
    });
});

it('does not duplicate summaries when run twice', function () {
    $presupuesto = Presupuesto::factory()->create();
    DetallePresupuesto::factory()->for($presupuesto)->create();

    $contrato = Contrato::factory()->for($presupuesto)->create([
        'fecha_inicio' => '2026-04-01',
        'fecha_fin' => '2026-07-01',
    ]);

    $contrato->generarResumenesMensuales();
    $contrato->generarResumenesMensuales();

    expect($contrato->resumenesMensuales()->count())->toBe(3);
});
