<?php

use App\Filament\Client\Pages\ContractsPage;
use App\Filament\Staff\Resources\ContractPeriods\Pages\ListContractPeriods;
use App\Models\Client;
use App\Models\ContractPeriod;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/*
 * RNF-3, RNF-4 and RNF-5. Data is loaded in bulk and the time is the best of three runs, so a
 * busy machine does not fail a threshold the system meets. Run apart with
 * `php artisan test --group=performance`, or skip with `--exclude-group=performance`.
 */

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    app()->setLocale('es');
    // 12:00 del 15 de octubre de 2026 en Argentina.
    Carbon::setTestNow(CarbonImmutable::parse('2026-10-15 15:00:00', 'UTC'));
});

afterEach(fn () => Carbon::setTestNow());

/**
 * Loads active contracts with plain bulk inserts, each one with the given months of periods
 * counting back from September 2026, spread over some clients. Every third period is paid in
 * part and every fifth one has gone overdue.
 *
 * @return array{clients: list<int>, contracts: list<int>}
 */
function seedContractsInBulk(int $contracts, int $months, int $clients = 50): array
{
    $author = User::factory()->admin()->create()->id;
    $clientIds = Client::factory()->count($clients)->create()->pluck('id')->all();
    $now = now()->toDateTimeString();

    foreach (array_chunk(range(1, $contracts), 250) as $chunk) {
        DB::table('budgets')->insert(array_map(fn (int $n): array => [
            'client_id' => $clientIds[$n % $clients],
            'title' => "Abono de prueba {$n}",
            'modality' => 'monthly',
            'issue_date' => '2025-01-01',
            'validity_date' => '2026-12-31',
            'status' => 'accepted',
            'subtotal' => '1000.00',
            'discount_amount' => '0.00',
            'total' => '1000.00',
            'created_by' => $author,
            'created_at' => $now,
            'updated_at' => $now,
        ], $chunk));
    }

    $budgets = DB::table('budgets')->orderBy('id')->get(['id', 'client_id']);

    foreach ($budgets->chunk(250) as $chunk) {
        DB::table('contracts')->insert($chunk->map(fn ($budget): array => [
            'budget_id' => $budget->id,
            'client_id' => $budget->client_id,
            'start_date' => CarbonImmutable::parse('2026-09-01')->subMonths($months - 1)->toDateString(),
            'term_months' => null,
            'term_end_month' => null,
            'due_day' => 10,
            'monthly_amount' => '1000.00',
            'status' => 'active',
            'created_by' => $author,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all());
    }

    $contractRows = DB::table('contracts')->orderBy('id')->get(['id', 'client_id']);
    $rows = [];

    foreach ($contractRows as $contract) {
        foreach (range(0, $months - 1) as $offset) {
            $month = CarbonImmutable::parse('2026-09-01')->subMonths($months - 1 - $offset);
            $n = $contract->id + $offset;

            $rows[] = [
                'contract_id' => $contract->id,
                'client_id' => $contract->client_id,
                'month' => $month->toDateString(),
                'due_date' => $month->addDays(9)->toDateString(),
                'fee' => '1000.00',
                'total' => '1000.00',
                'paid_amount' => $n % 3 === 0 ? '400.00' : '0.00',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
    }

    foreach (array_chunk($rows, 500) as $chunk) {
        DB::table('contract_periods')->insert($chunk);
    }

    return ['clients' => $clientIds, 'contracts' => $contractRows->pluck('id')->all()];
}

it('RNF-3: mostrar el listado de 5.000 períodos tarda menos de 2 segundos', function () {
    Filament::setCurrentPanel('staff');
    $this->actingAs(User::factory()->staff()->create());

    seedContractsInBulk(contracts: 500, months: 10);

    expect(ContractPeriod::query()->count())->toBe(5000);

    Livewire::test(ListContractPeriods::class)->assertSuccessful();

    $elapsed = fastestOf(3, fn () => Livewire::test(ListContractPeriods::class)->assertSuccessful());

    expect($elapsed)->toBeLessThan(2.0);
})->group('performance');

it('RNF-3: filtrar el listado de 5.000 períodos por cliente, estado de pago y vencidos tarda menos de 2 segundos', function () {
    Filament::setCurrentPanel('staff');
    $this->actingAs(User::factory()->staff()->create());

    $seeded = seedContractsInBulk(contracts: 500, months: 10);

    $byClient = fastestOf(3, fn () => Livewire::test(ListContractPeriods::class)
        ->filterTable('client_id', $seeded['clients'][0])
        ->assertSuccessful());

    $byStatus = fastestOf(3, fn () => Livewire::test(ListContractPeriods::class)
        ->filterTable('payment_status', 'partial')
        ->assertSuccessful());

    $overdue = fastestOf(3, fn () => Livewire::test(ListContractPeriods::class)
        ->filterTable('overdue', true)
        ->assertSuccessful());

    expect($byClient)->toBeLessThan(2.0)
        ->and($byStatus)->toBeLessThan(2.0)
        ->and($overdue)->toBeLessThan(2.0);
})->group('performance');

it('RNF-3: el listado de períodos no hace una consulta por fila', function () {
    Filament::setCurrentPanel('staff');
    $this->actingAs(User::factory()->staff()->create());

    seedContractsInBulk(contracts: 500, months: 10);

    DB::enableQueryLog();
    DB::flushQueryLog();

    Livewire::test(ListContractPeriods::class)->assertSuccessful();

    $queries = count(DB::getQueryLog());

    DB::disableQueryLog();

    // One page of rows plus the client of each: the count does not follow the 5.000 periods.
    expect($queries)->toBeLessThan(40);
})->group('performance');

it('RNF-4: generar los períodos del mes para 500 contratos con 12 períodos cada uno tarda menos de 10 segundos', function () {
    seedContractsInBulk(contracts: 500, months: 12);

    expect(ContractPeriod::query()->count())->toBe(6000);

    // Hasta septiembre ya están generados: falta el período de octubre de cada contrato.
    $timings = [];

    foreach (range(1, 3) as $run) {
        DB::table('period_histories')->whereIn('contract_period_id', DB::table('contract_periods')->where('month', '2026-10-01')->select('id'))->delete();
        DB::table('contract_periods')->where('month', '2026-10-01')->delete();

        $timings[] = secondsTaken(fn () => Artisan::call('contracts:generate-periods'));
    }

    expect(ContractPeriod::query()->where('month', '2026-10-01')->count())->toBe(500)
        ->and(ContractPeriod::query()->count())->toBe(6500)
        ->and(min($timings))->toBeLessThan(10.0);
})->group('performance');

it('RNF-4: la generación del mes no hace más de unas pocas consultas por contrato', function () {
    seedContractsInBulk(contracts: 100, months: 12);

    DB::enableQueryLog();
    DB::flushQueryLog();

    Artisan::call('contracts:generate-periods');

    $queries = count(DB::getQueryLog());

    DB::disableQueryLog();

    expect(ContractPeriod::query()->where('month', '2026-10-01')->count())->toBe(100)
        ->and($queries)->toBeLessThan(100 * 10);
})->group('performance');

/**
 * A client with 5 contracts of 12 periods (60 periods) and 200 payments spread over them.
 *
 * @return array{account: User, client: int}
 */
function seedPortalClient(): array
{
    $seeded = seedContractsInBulk(contracts: 5, months: 12, clients: 1);
    $clientId = $seeded['clients'][0];
    $account = User::factory()->client()->create(['client_id' => $clientId]);
    $author = User::factory()->staff()->create()->id;
    $now = now()->toDateTimeString();

    $periodIds = DB::table('contract_periods')->where('client_id', $clientId)->orderBy('id')->pluck('id')->all();
    $payments = [];

    foreach (range(0, 199) as $n) {
        $payments[] = [
            'contract_period_id' => $periodIds[$n % count($periodIds)],
            'paid_on' => '2026-09-15',
            'amount' => '1.00',
            'method' => 'cash',
            'reference' => "Recibo {$n}",
            'created_by' => $author,
            // Every fifth one was voided and must not be loaded.
            'voided_at' => $n % 5 === 0 ? $now : null,
            'voided_by' => $n % 5 === 0 ? $author : null,
            'void_justification' => $n % 5 === 0 ? 'Error de carga' : null,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    DB::table('payments')->insert($payments);

    return ['account' => $account, 'client' => $clientId];
}

it('RNF-5: mostrar el portal de contratos de un cliente con 60 períodos y 200 pagos tarda menos de 2 segundos', function () {
    Filament::setCurrentPanel('client');
    $seeded = seedPortalClient();
    $this->actingAs($seeded['account']);

    expect(ContractPeriod::query()->where('client_id', $seeded['client'])->count())->toBe(60)
        ->and(DB::table('payments')->count())->toBe(200);

    Livewire::test(ContractsPage::class)->assertSuccessful();

    $elapsed = fastestOf(3, fn () => Livewire::test(ContractsPage::class)->assertSuccessful()->assertSee('Recibo 1'));

    expect($elapsed)->toBeLessThan(2.0);
})->group('performance');

it('RNF-5: el portal de contratos no hace una consulta por período ni por pago', function () {
    Filament::setCurrentPanel('client');
    $seeded = seedPortalClient();
    $this->actingAs($seeded['account']);

    Livewire::test(ContractsPage::class)->assertSuccessful();

    DB::enableQueryLog();
    DB::flushQueryLog();

    Livewire::test(ContractsPage::class)->assertSuccessful();

    $queries = count(DB::getQueryLog());

    DB::disableQueryLog();

    expect($queries)->toBeLessThan(40);
})->group('performance');
