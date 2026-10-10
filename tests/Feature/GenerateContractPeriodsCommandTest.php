<?php

use App\Actions\GenerateContractPeriodsAction;
use App\Enums\ContractHistoryField;
use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Models\ContractHistory;
use App\Models\ContractPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

beforeEach(function () {
    app()->setLocale('es');
    // 12:00 del 15 de octubre de 2026 en Argentina.
    Carbon::setTestNow(CarbonImmutable::parse('2026-10-15 15:00:00', 'UTC'));
});

afterEach(fn () => Carbon::setTestNow());

function periodMonths(Contract $contract): array
{
    return $contract->periods()->get()->map(fn (ContractPeriod $period) => $period->month->format('Y-m'))->all();
}

it('RF-36: genera el período del mes de los contratos vigentes', function () {
    $contract = Contract::factory()->startingOn('2026-10-01')->indefinite()->create();

    $this->artisan('contracts:generate-periods')->assertSuccessful();

    expect(periodMonths($contract))->toBe(['2026-10']);
});

it('RF-36: repone un mes que no corrió', function () {
    $contract = Contract::factory()->startingOn('2026-08-01')->indefinite()->create();
    ContractPeriod::factory()->create(['contract_id' => $contract->id, 'month' => '2026-08-01']);

    $this->artisan('contracts:generate-periods')->assertSuccessful();

    expect(periodMonths($contract))->toBe(['2026-08', '2026-09', '2026-10']);
});

it('RF-36: correrlo dos veces no duplica ningún período', function () {
    $contract = Contract::factory()->startingOn('2026-08-01')->indefinite()->create();

    $this->artisan('contracts:generate-periods')->assertSuccessful();
    $this->artisan('contracts:generate-periods')->assertSuccessful();

    expect(periodMonths($contract))->toBe(['2026-08', '2026-09', '2026-10']);
});

it('RF-22, RF-36: no genera períodos de los contratos cancelados ni de los finalizados', function () {
    $cancelled = Contract::factory()->cancelled()->startingOn('2026-10-01')->create();
    $finished = Contract::factory()->finished()->startingOn('2026-10-01')->create();

    $this->artisan('contracts:generate-periods')->assertSuccessful();

    expect(periodMonths($cancelled))->toBe([])
        ->and(periodMonths($finished))->toBe([]);
});

it('RF-35, RF-36: sigue generando los períodos de un contrato cuyo cliente está archivado', function () {
    $contract = Contract::factory()->startingOn('2026-10-01')->create();
    $contract->client->delete();

    $this->artisan('contracts:generate-periods')->assertSuccessful();

    expect(periodMonths($contract))->toBe(['2026-10']);
});

it('RF-29: finaliza el contrato de plazo fijo cuando empieza el mes siguiente al último del plazo, con el sistema como autor', function () {
    $contract = Contract::factory()->startingOn('2026-06-10')->fixedTerm(3)->create();

    $this->artisan('contracts:generate-periods')->assertSuccessful();

    $entry = ContractHistory::query()
        ->where('contract_id', $contract->id)
        ->where('field', ContractHistoryField::StatusChanged)
        ->sole();

    expect($contract->fresh()->status)->toBe(ContractStatus::Finished)
        ->and($entry->old_value)->toBe(['status' => 'active'])
        ->and($entry->new_value)->toBe(['status' => 'finished'])
        ->and($entry->author_id)->toBeNull()
        // Los tres meses del plazo se generaron antes de finalizarlo.
        ->and(periodMonths($contract))->toBe(['2026-06', '2026-07', '2026-08']);
});

it('RF-29: el contrato sigue vigente durante el último mes del plazo', function () {
    $contract = Contract::factory()->startingOn('2026-08-01')->fixedTerm(3)->create();

    $this->artisan('contracts:generate-periods')->assertSuccessful();

    expect($contract->fresh()->status)->toBe(ContractStatus::Active)
        ->and(periodMonths($contract))->toBe(['2026-08', '2026-09', '2026-10']);
});

it('RF-29: finaliza el primer minuto del mes siguiente en la hora de negocio', function () {
    $contract = Contract::factory()->startingOn('2026-08-01')->fixedTerm(3)->create();

    // 23:59 del 31 de octubre en Argentina: todavía es octubre.
    Carbon::setTestNow(CarbonImmutable::parse('2026-11-01 02:59:00', 'UTC'));
    $this->artisan('contracts:generate-periods')->assertSuccessful();
    expect($contract->fresh()->status)->toBe(ContractStatus::Active);

    // 00:10 del 1 de noviembre en Argentina.
    Carbon::setTestNow(CarbonImmutable::parse('2026-11-01 03:10:00', 'UTC'));
    $this->artisan('contracts:generate-periods')->assertSuccessful();
    expect($contract->fresh()->status)->toBe(ContractStatus::Finished);
});

it('RF-29: no finaliza los contratos indefinidos ni los cancelados', function () {
    $indefinite = Contract::factory()->startingOn('2025-01-01')->indefinite()->create();
    $cancelled = Contract::factory()->cancelled()->startingOn('2026-01-01')->fixedTerm(2)->create();

    $this->artisan('contracts:generate-periods')->assertSuccessful();

    expect($indefinite->fresh()->status)->toBe(ContractStatus::Active)
        ->and($cancelled->fresh()->status)->toBe(ContractStatus::Cancelled);
});

it('RF-29: finalizar de nuevo no repite el asiento', function () {
    Contract::factory()->startingOn('2026-06-10')->fixedTerm(3)->create();

    $this->artisan('contracts:generate-periods')->assertSuccessful();
    $this->artisan('contracts:generate-periods')->assertSuccessful();

    expect(ContractHistory::query()->where('field', ContractHistoryField::StatusChanged)->count())->toBe(1);
});

it('RF-36: si un contrato falla sigue con los demás y avisa con un código de error', function () {
    $broken = Contract::factory()->startingOn('2026-10-01')->create();
    $healthy = Contract::factory()->startingOn('2026-10-01')->create();

    app()->bind(GenerateContractPeriodsAction::class, fn () => new class($broken->id) extends GenerateContractPeriodsAction
    {
        public function __construct(private readonly int $failingId) {}

        public function handle(Contract $contract): Collection
        {
            if ($contract->id === $this->failingId) {
                throw new RuntimeException('Falla simulada');
            }

            return parent::handle($contract);
        }
    });

    $this->artisan('contracts:generate-periods')->assertFailed();

    expect(periodMonths($broken))->toBe([])
        ->and(periodMonths($healthy))->toBe(['2026-10']);
});

it('RF-36: el comando está programado todos los días a las 00:10 de la hora de negocio', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains((string) $event->command, 'contracts:generate-periods'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('10 0 * * *')
        ->and((string) $event->timezone)->toBe(config('app.business_timezone'));
});
