<?php

use App\Filament\Staff\Resources\Clients\Pages\ListClients;
use App\Models\Client;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/*
 * RNF-5. Run apart with `php artisan test --group=performance`, or skip it on a slow
 * machine with `--exclude-group=performance`.
 */

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    app()->setLocale('es');
    Filament::setCurrentPanel('staff');

    $this->actingAs(User::factory()->staff()->create());

    seedClientsInBulk(5000);

    // One unmeasured render, so booting the framework and Filament is not part of the timing.
    Livewire::test(ListClients::class)->assertSuccessful();
});

it('RNF-5: el listado de 5.000 clientes se muestra en menos de 2 segundos', function () {
    expect(Client::withTrashed()->count())->toBe(5000);

    $elapsed = secondsTaken(fn () => Livewire::test(ListClients::class)->assertSuccessful());

    expect($elapsed)->toBeLessThan(2.0);
})->group('performance');

it('RNF-5: con búsqueda, filtro y orden sobre 5.000 clientes sigue por debajo de 2 segundos', function (string $term) {
    $elapsed = secondsTaken(fn () => Livewire::test(ListClients::class)
        ->searchTable($term)
        ->filterTable('status', 'active')
        ->sortTable('display_name')
        ->assertSuccessful());

    expect($elapsed)->toBeLessThan(2.0);
})->with([
    'por nombre' => 'nombre12',
    'por documento' => '10001234',
    'por teléfono de un contacto' => '11-00001234',
    'por email de un contacto' => 'contacto4321@',
    'sin coincidencias' => 'zzzz-no-existe',
])->group('performance');

it('RNF-5: ordenar por fecha de alta e incluir archivados también cumple el umbral', function () {
    $elapsed = secondsTaken(fn () => Livewire::test(ListClients::class)
        ->filterTable('trashed', true)
        ->filterTable('person_type', 'company')
        ->sortTable('created_at', 'desc')
        ->assertSuccessful());

    expect($elapsed)->toBeLessThan(2.0);
})->group('performance');

it('RNF-5: la cantidad de consultas no crece con el volumen de clientes', function () {
    DB::enableQueryLog();
    DB::flushQueryLog();

    Livewire::test(ListClients::class)->searchTable('nombre1')->assertSuccessful();

    $queries = count(DB::getQueryLog());

    DB::disableQueryLog();

    // A page of 25 rows must not trigger one query per row.
    expect($queries)->toBeLessThan(30);
})->group('performance');
