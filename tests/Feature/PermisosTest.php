<?php

use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\DetallePresupuesto;
use App\Models\Presupuesto;
use App\Models\User;
use Database\Seeders\RolesYPermisosSeeder;

beforeEach(function () {
    $this->seed(RolesYPermisosSeeder::class);
});

it('lets staff view, create, update and delete a cliente', function () {
    $staff = User::factory()->create();
    $cliente = Cliente::factory()->create();

    expect($staff->can('viewAny', Cliente::class))->toBeTrue()
        ->and($staff->can('view', $cliente))->toBeTrue()
        ->and($staff->can('create', Cliente::class))->toBeTrue()
        ->and($staff->can('update', $cliente))->toBeTrue()
        ->and($staff->can('delete', $cliente))->toBeTrue();
});

it('blocks a cliente role from creating or updating clientes', function () {
    $clienteUser = User::factory()->cliente()->create();
    $cliente = Cliente::factory()->create();

    expect($clienteUser->can('viewAny', Cliente::class))->toBeFalse()
        ->and($clienteUser->can('view', $cliente))->toBeFalse()
        ->and($clienteUser->can('create', Cliente::class))->toBeFalse()
        ->and($clienteUser->can('update', $cliente))->toBeFalse()
        ->and($clienteUser->can('delete', $cliente))->toBeFalse();
});

it('blocks a cliente user from viewing a contrato that belongs to another cliente', function () {
    $presupuestoPropio = Presupuesto::factory()->create();
    DetallePresupuesto::factory()->for($presupuestoPropio)->create();
    $contratoPropio = Contrato::factory()->for($presupuestoPropio)->create();

    $presupuestoAjeno = Presupuesto::factory()->create();
    DetallePresupuesto::factory()->for($presupuestoAjeno)->create();
    $contratoAjeno = Contrato::factory()->for($presupuestoAjeno)->create();

    $clienteUser = User::factory()->cliente($presupuestoPropio->cliente)->create();

    expect($clienteUser->can('view', $contratoPropio))->toBeTrue()
        ->and($clienteUser->can('view', $contratoAjeno))->toBeFalse();
});
