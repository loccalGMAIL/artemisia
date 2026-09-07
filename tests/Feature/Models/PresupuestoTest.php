<?php

use App\Enums\EstadoPresupuesto;
use App\Models\Contrato;
use App\Models\DetallePresupuesto;
use App\Models\Presupuesto;
use App\Models\Produccion;
use App\Models\Rubro;
use App\Models\Servicio;

describe('detalle subtotal and total', function () {
    it('calculates the subtotal when a line is saved', function () {
        $presupuesto = Presupuesto::factory()->create();

        $detalle = DetallePresupuesto::factory()->for($presupuesto)->create([
            'cantidad' => 3,
            'precio_unitario' => 1500,
        ]);

        expect($detalle->fresh()->subtotal)->toBe('4500.00');
    });

    it('recalculates the total when a line is saved or deleted', function () {
        $presupuesto = Presupuesto::factory()->create();

        $uno = DetallePresupuesto::factory()->for($presupuesto)->create([
            'cantidad' => 1,
            'precio_unitario' => 1000,
        ]);
        DetallePresupuesto::factory()->for($presupuesto)->create([
            'cantidad' => 2,
            'precio_unitario' => 500,
        ]);

        expect($presupuesto->fresh()->total)->toBe('2000.00');

        $uno->delete();

        expect($presupuesto->fresh()->total)->toBe('1000.00');
    });
});

describe('esRecurrente', function () {
    it('is true when every line belongs to a recurring rubro', function () {
        $rubro = Rubro::factory()->recurrente()->create();
        $servicio = Servicio::factory()->for($rubro)->create();
        $presupuesto = Presupuesto::factory()->create();
        DetallePresupuesto::factory()->for($presupuesto)->for($servicio)->create();

        expect($presupuesto->esRecurrente())->toBeTrue();
    });

    it('is false when every line belongs to a non-recurring rubro', function () {
        $rubro = Rubro::factory()->noRecurrente()->create();
        $servicio = Servicio::factory()->for($rubro)->create();
        $presupuesto = Presupuesto::factory()->create();
        DetallePresupuesto::factory()->for($presupuesto)->for($servicio)->create();

        expect($presupuesto->esRecurrente())->toBeFalse();
    });

    it('is false when lines mix recurring and non-recurring rubros', function () {
        $recurrente = Servicio::factory()->for(Rubro::factory()->recurrente())->create();
        $puntual = Servicio::factory()->for(Rubro::factory()->noRecurrente())->create();
        $presupuesto = Presupuesto::factory()->create();
        DetallePresupuesto::factory()->for($presupuesto)->for($recurrente)->create();
        DetallePresupuesto::factory()->for($presupuesto)->for($puntual)->create();

        expect($presupuesto->esRecurrente())->toBeFalse();
    });

    it('is false when the budget has no lines with a service', function () {
        $presupuesto = Presupuesto::factory()->create();
        DetallePresupuesto::factory()->for($presupuesto)->create(['servicio_id' => null]);

        expect($presupuesto->esRecurrente())->toBeFalse();
    });
});

describe('confirmar', function () {
    it('creates a 3-month contract for a recurring budget', function () {
        $rubro = Rubro::factory()->recurrente()->create();
        $servicio = Servicio::factory()->for($rubro)->create();
        $presupuesto = Presupuesto::factory()->create(['fecha' => '2026-04-01']);
        DetallePresupuesto::factory()->for($presupuesto)->for($servicio)->create();

        $resultado = $presupuesto->confirmar();

        expect($resultado)->toBeInstanceOf(Contrato::class)
            ->and($resultado->fecha_inicio->toDateString())->toBe('2026-04-01')
            ->and($resultado->fecha_fin->toDateString())->toBe('2026-07-01')
            ->and($presupuesto->fresh()->estado)->toBe(EstadoPresupuesto::Confirmado);
    });

    it('creates a pending production for a non-recurring budget', function () {
        $rubro = Rubro::factory()->noRecurrente()->create();
        $servicio = Servicio::factory()->for($rubro)->create();
        $presupuesto = Presupuesto::factory()->create();
        DetallePresupuesto::factory()->for($presupuesto)->for($servicio)->create();

        $resultado = $presupuesto->confirmar();

        expect($resultado)->toBeInstanceOf(Produccion::class);
    });

    it('does not duplicate the contract when confirmed twice', function () {
        $rubro = Rubro::factory()->recurrente()->create();
        $servicio = Servicio::factory()->for($rubro)->create();
        $presupuesto = Presupuesto::factory()->create();
        DetallePresupuesto::factory()->for($presupuesto)->for($servicio)->create();

        $primero = $presupuesto->confirmar();
        $segundo = $presupuesto->fresh()->confirmar();

        expect($segundo->is($primero))->toBeTrue()
            ->and(Contrato::count())->toBe(1);
    });
});
