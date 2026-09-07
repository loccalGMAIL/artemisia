<?php

use App\Models\CategoriaCliente;
use App\Models\Servicio;
use Illuminate\Support\Carbon;

it('prefers the price for the client category over the unique price', function () {
    $categoria = CategoriaCliente::factory()->create();
    $servicio = Servicio::factory()->create();

    $servicio->precios()->create([
        'categoria_cliente_id' => null,
        'precio' => 1000,
        'vigente_desde' => '2026-01-01',
    ]);
    $servicio->precios()->create([
        'categoria_cliente_id' => $categoria->id,
        'precio' => 2500,
        'vigente_desde' => '2026-01-01',
    ]);

    expect($servicio->precioVigente($categoria->id))->toBe('2500.00');
});

it('falls back to the unique price when the category has none', function () {
    $categoria = CategoriaCliente::factory()->create();
    $servicio = Servicio::factory()->create();

    $servicio->precios()->create([
        'categoria_cliente_id' => null,
        'precio' => 1000,
        'vigente_desde' => '2026-01-01',
    ]);

    expect($servicio->precioVigente($categoria->id))->toBe('1000.00');
});

it('returns the most recent price among several vigente_desde rows for the same category', function () {
    $categoria = CategoriaCliente::factory()->create();
    $servicio = Servicio::factory()->create();

    $servicio->precios()->create([
        'categoria_cliente_id' => $categoria->id,
        'precio' => 1000,
        'vigente_desde' => '2026-01-01',
    ]);
    $servicio->precios()->create([
        'categoria_cliente_id' => $categoria->id,
        'precio' => 1200,
        'vigente_desde' => '2026-04-01',
    ]);

    expect($servicio->precioVigente($categoria->id, Carbon::parse('2026-06-01')))->toBe('1200.00');
});

it('ignores prices whose vigente_desde is in the future', function () {
    $categoria = CategoriaCliente::factory()->create();
    $servicio = Servicio::factory()->create();

    $servicio->precios()->create([
        'categoria_cliente_id' => $categoria->id,
        'precio' => 1000,
        'vigente_desde' => '2026-01-01',
    ]);
    $servicio->precios()->create([
        'categoria_cliente_id' => $categoria->id,
        'precio' => 9999,
        'vigente_desde' => '2099-01-01',
    ]);

    expect($servicio->precioVigente($categoria->id, Carbon::parse('2026-06-01')))->toBe('1000.00');
});

it('returns null when the service has no price at all', function () {
    $categoria = CategoriaCliente::factory()->create();
    $servicio = Servicio::factory()->create();

    expect($servicio->precioVigente($categoria->id))->toBeNull();
});
