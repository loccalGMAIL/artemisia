<?php

use App\Enums\EstadoPago;
use App\Filament\Cliente\Resources\Contratos\ContratoResource;
use App\Filament\Cliente\Resources\Produccions\ProduccionResource;
use App\Filament\Cliente\Resources\ResumenMensuals\ResumenMensualResource;
use App\Models\Contrato;
use App\Models\DetallePresupuesto;
use App\Models\Presupuesto;
use App\Models\Produccion;
use App\Models\ResumenMensual;
use App\Models\User;

it('only shows contratos belonging to the authenticated cliente', function () {
    $presupuestoA = Presupuesto::factory()->create();
    DetallePresupuesto::factory()->for($presupuestoA)->create();
    $contratoA = Contrato::factory()->for($presupuestoA)->create();

    $presupuestoB = Presupuesto::factory()->create();
    DetallePresupuesto::factory()->for($presupuestoB)->create();
    $contratoB = Contrato::factory()->for($presupuestoB)->create();

    $this->actingAs(User::factory()->cliente($presupuestoA->cliente)->create());

    $ids = ContratoResource::getEloquentQuery()->pluck('id');

    expect($ids)->toContain($contratoA->id)
        ->and($ids)->not->toContain($contratoB->id);
});

it('only shows producciones belonging to the authenticated cliente', function () {
    $presupuestoA = Presupuesto::factory()->create();
    $produccionA = Produccion::factory()->for($presupuestoA)->create();

    $presupuestoB = Presupuesto::factory()->create();
    $produccionB = Produccion::factory()->for($presupuestoB)->create();

    $this->actingAs(User::factory()->cliente($presupuestoA->cliente)->create());

    $ids = ProduccionResource::getEloquentQuery()->pluck('id');

    expect($ids)->toContain($produccionA->id)
        ->and($ids)->not->toContain($produccionB->id);
});

it('only shows resumenes mensuales belonging to the authenticated cliente', function () {
    $presupuestoA = Presupuesto::factory()->create();
    $contratoA = Contrato::factory()->for($presupuestoA)->create();
    $resumenA = ResumenMensual::factory()->for($contratoA)->create(['estado_pago' => EstadoPago::Pendiente]);

    $presupuestoB = Presupuesto::factory()->create();
    $contratoB = Contrato::factory()->for($presupuestoB)->create();
    $resumenB = ResumenMensual::factory()->for($contratoB)->create(['estado_pago' => EstadoPago::Pendiente]);

    $this->actingAs(User::factory()->cliente($presupuestoA->cliente)->create());

    $ids = ResumenMensualResource::getEloquentQuery()->pluck('id');

    expect($ids)->toContain($resumenA->id)
        ->and($ids)->not->toContain($resumenB->id);
});
