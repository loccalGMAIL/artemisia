<?php

use App\Enums\PieceStatus;
use App\Filament\Staff\Resources\Pieces\Pages\ListPieces;
use App\Models\Budget;
use App\Models\Client;
use App\Models\Piece;
use App\Models\User;
use App\Models\WorkCategory;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/*
 * RNF-2. Data is loaded in bulk and the time is the best of three runs, so a busy machine does
 * not fail a threshold the system meets. Run apart with `php artisan test --group=performance`,
 * or skip with `--exclude-group=performance`.
 */

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    app()->setLocale('es');
    Filament::setCurrentPanel('staff');
    $this->travelTo(now()->setDate(2026, 10, 15)->setTime(10, 0));
    $this->actor = User::factory()->staff()->create();
    $this->actingAs($this->actor);
});

/**
 * Loads pieces with plain bulk inserts, spread over some accepted budgets, a few owners and all
 * the states. Every fortieth piece is discarded, every third one has a date in the past.
 *
 * @return array{client: int, owner: int}
 */
function seedPiecesInBulk(int $count, int $budgets = 200, int $owners = 10): array
{
    $author = User::factory()->admin()->create()->id;
    $ownerIds = User::factory()->staff()->count($owners)->create()->pluck('id')->all();
    $category = WorkCategory::factory()->create()->id;
    $clientIds = Client::factory()->count(40)->create()->pluck('id')->all();
    $budgetIds = [];

    foreach (range(1, $budgets) as $n) {
        $budgetIds[] = Budget::factory()->accepted()->create(['client_id' => $clientIds[$n % 40]])->id;
    }

    $states = array_map(fn (PieceStatus $status): string => $status->value, PieceStatus::cases());
    $now = now()->toDateTimeString();

    foreach (array_chunk(range(1, $count), 500) as $chunk) {
        DB::table('pieces')->insert(array_map(fn (int $n): array => [
            'budget_id' => $budgetIds[$n % $budgets],
            'budget_item_id' => null,
            'name' => "Pieza de prueba {$n}",
            'description' => null,
            'work_category_id' => $category,
            'status' => $states[$n % 6],
            'assignee_id' => $n % 4 === 0 ? null : $ownerIds[$n % $owners],
            'due_date' => $n % 3 === 0 ? '2026-09-01' : '2026-12-01',
            'created_by' => $author,
            'created_at' => now()->subMinutes($n)->toDateTimeString(),
            'updated_at' => $now,
            'deleted_at' => $n % 40 === 0 ? $now : null,
        ], $chunk));
    }

    return ['client' => Budget::query()->value('client_id'), 'owner' => $ownerIds[0]];
}

it('RNF-2: el listado de 5.000 piezas se muestra en menos de 2 segundos', function () {
    seedPiecesInBulk(5000);

    expect(Piece::withTrashed()->count())->toBe(5000);

    Livewire::test(ListPieces::class)->assertSuccessful();

    $elapsed = fastestOf(3, fn () => Livewire::test(ListPieces::class)->assertSuccessful());

    expect($elapsed)->toBeLessThan(2.0);
})->group('performance');

it('RNF-2: con cada filtro y orden sobre 5.000 piezas sigue por debajo de 2 segundos', function () {
    $seeded = seedPiecesInBulk(5000);

    Livewire::test(ListPieces::class)->assertSuccessful();

    $filters = [
        'estado' => ['status', PieceStatus::InProduction->value],
        'responsable' => ['assignee_id', $seeded['owner']],
        'cliente' => ['client', $seeded['client']],
        'atrasadas' => ['overdue', true],
        'mis piezas delegadas' => ['mine', true],
    ];

    foreach ($filters as $name => [$filter, $value]) {
        $elapsed = fastestOf(3, fn () => Livewire::test(ListPieces::class)
            ->filterTable($filter, $value)
            ->sortTable('due_date', 'asc')
            ->assertSuccessful());

        expect($elapsed)->toBeLessThan(2.0, "El filtro por {$name} tardó {$elapsed} s.");
    }
})->group('performance');

it('RNF-2: el listado carga los datos de cada fila de una vez y no consulta fila por fila', function () {
    seedPiecesInBulk(5000);

    Livewire::test(ListPieces::class)->assertSuccessful();

    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    Livewire::test(ListPieces::class)->assertSuccessful();

    expect(count($queries))->toBeLessThan(30);
})->group('performance');
