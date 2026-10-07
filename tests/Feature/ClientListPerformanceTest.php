<?php

use App\Filament\Staff\Resources\Clients\Pages\ListClients;
use App\Models\Client;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/*
 * RNF-5. The data is loaded in bulk and is deterministic, so the measurement does not depend
 * on faker. Run apart with `php artisan test --group=performance`, or skip it on a slow
 * machine with `--exclude-group=performance`.
 */

const PERFORMANCE_CLIENTS = 5000;
const LIST_THRESHOLD_SECONDS = 2.0;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    app()->setLocale('es');
    Filament::setCurrentPanel('staff');

    $this->actingAs(User::factory()->staff()->create());
    $author = User::factory()->admin()->create()->id;
    $now = now()->toDateTimeString();

    foreach (array_chunk(range(1, PERFORMANCE_CLIENTS), 500) as $chunk) {
        Client::query()->insert(array_map(fn (int $n): array => [
            'person_type' => $n % 4 === 0 ? 'company' : 'individual',
            'first_name' => $n % 4 === 0 ? null : "Nombre{$n}",
            'last_name' => $n % 4 === 0 ? null : "Apellido{$n}",
            'company_name' => $n % 4 === 0 ? "Empresa {$n} S.A." : null,
            'document' => (string) (10_000_000 + $n),
            'status' => $n % 5 === 0 ? 'inactive' : 'active',
            'created_by' => $author,
            'created_at' => now()->subMinutes($n)->toDateTimeString(),
            'updated_at' => $now,
            'deleted_at' => $n % 50 === 0 ? $now : null,
        ], $chunk));
    }

    $ids = Client::withTrashed()->pluck('id')->all();

    foreach (array_chunk($ids, 500) as $chunk) {
        DB::table('client_contacts')->insert(array_map(fn (int $id): array => [
            'client_id' => $id,
            'name' => "Contacto {$id}",
            'phone' => '11-'.str_pad((string) $id, 8, '0', STR_PAD_LEFT),
            'email' => "contacto{$id}@example.com",
            'is_primary' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ], $chunk));
    }

    // One unmeasured render, so booting the framework and Filament is not part of the timing.
    Livewire::test(ListClients::class)->assertSuccessful();
});

function seconds(Closure $callback): float
{
    $start = microtime(true);

    $callback();

    return microtime(true) - $start;
}

it('RNF-5: el listado de 5.000 clientes se muestra en menos de 2 segundos', function () {
    expect(Client::withTrashed()->count())->toBe(PERFORMANCE_CLIENTS);

    $elapsed = seconds(fn () => Livewire::test(ListClients::class)->assertSuccessful());

    expect($elapsed)->toBeLessThan(LIST_THRESHOLD_SECONDS);
})->group('performance');

it('RNF-5: con búsqueda, filtro y orden sobre 5.000 clientes sigue por debajo de 2 segundos', function (string $term) {
    $elapsed = seconds(fn () => Livewire::test(ListClients::class)
        ->searchTable($term)
        ->filterTable('status', 'active')
        ->sortTable('display_name')
        ->assertSuccessful());

    expect($elapsed)->toBeLessThan(LIST_THRESHOLD_SECONDS);
})->with([
    'por nombre' => 'nombre12',
    'por documento' => '10001234',
    'por teléfono de un contacto' => '11-00001234',
    'por email de un contacto' => 'contacto4321@',
    'sin coincidencias' => 'zzzz-no-existe',
])->group('performance');

it('RNF-5: ordenar por fecha de alta e incluir archivados también cumple el umbral', function () {
    $elapsed = seconds(fn () => Livewire::test(ListClients::class)
        ->filterTable('trashed', true)
        ->filterTable('person_type', 'company')
        ->sortTable('created_at', 'desc')
        ->assertSuccessful());

    expect($elapsed)->toBeLessThan(LIST_THRESHOLD_SECONDS);
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
