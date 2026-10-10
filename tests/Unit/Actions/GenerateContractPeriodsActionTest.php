<?php

use App\Actions\CreateContractAction;
use App\Actions\GenerateContractPeriodsAction;
use App\Enums\PeriodHistoryField;
use App\Models\Budget;
use App\Models\Contract;
use App\Models\ContractPeriod;
use App\Models\PeriodHistory;
use App\Models\User;
use App\Support\BusinessDate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

beforeEach(function () {
    app()->setLocale('es');
    // 12:00 del 15 de octubre de 2026 en Argentina.
    Carbon::setTestNow(CarbonImmutable::parse('2026-10-15 15:00:00', 'UTC'));
});

afterEach(fn () => Carbon::setTestNow());

function months(Contract $contract): array
{
    return $contract->periods()->get()->map(fn (ContractPeriod $period) => $period->month->format('Y-m'))->all();
}

it('RF-36: genera los períodos desde el mes de inicio hasta el mes actual', function () {
    $contract = Contract::factory()->startingOn('2026-07-20')->indefinite()->create(['due_day' => 10]);

    $created = app(GenerateContractPeriodsAction::class)->handle($contract);

    expect($created)->toHaveCount(4)
        ->and(months($contract))->toBe(['2026-07', '2026-08', '2026-09', '2026-10']);
});

it('RF-36: repone solo los meses que faltan', function () {
    $contract = Contract::factory()->startingOn('2026-07-20')->indefinite()->create();
    ContractPeriod::factory()->for($contract)->create(['month' => '2026-07-01']);
    ContractPeriod::factory()->for($contract)->create(['month' => '2026-08-01']);

    $created = app(GenerateContractPeriodsAction::class)->handle($contract);

    expect($created->map(fn (ContractPeriod $period) => $period->month->format('Y-m'))->all())->toBe(['2026-09', '2026-10'])
        ->and(months($contract))->toBe(['2026-07', '2026-08', '2026-09', '2026-10']);
});

it('RF-37, RF-38: generarlos de nuevo no duplica ningún período', function () {
    $contract = Contract::factory()->startingOn('2026-09-01')->indefinite()->create();

    app(GenerateContractPeriodsAction::class)->handle($contract);
    $second = app(GenerateContractPeriodsAction::class)->handle($contract);

    expect($second)->toBeEmpty()
        ->and(ContractPeriod::query()->where('contract_id', $contract->id)->count())->toBe(2)
        ->and(PeriodHistory::query()->count())->toBe(2);
});

it('RF-37: no genera nada mientras el mes de inicio no haya llegado', function (string $start) {
    $contract = Contract::factory()->startingOn($start)->indefinite()->create();

    expect(app(GenerateContractPeriodsAction::class)->handle($contract))->toBeEmpty()
        ->and(ContractPeriod::query()->count())->toBe(0);
})->with(['mes siguiente' => ['2026-11-01'], 'mucho más adelante' => ['2027-05-20']]);

it('RF-39: en un plazo fijo no genera más períodos que meses tenga el plazo', function () {
    $contract = Contract::factory()->startingOn('2026-07-20')->fixedTerm(3)->create();

    app(GenerateContractPeriodsAction::class)->handle($contract);

    expect(months($contract))->toBe(['2026-07', '2026-08', '2026-09']);
});

it('RF-39: un plazo fijo más largo que lo transcurrido llega solo hasta el mes actual', function () {
    $contract = Contract::factory()->startingOn('2026-07-20')->fixedTerm(12)->create();

    app(GenerateContractPeriodsAction::class)->handle($contract);

    expect(months($contract))->toBe(['2026-07', '2026-08', '2026-09', '2026-10']);
});

it('RF-40: cada período toma la cuota y el vencimiento del contrato, aunque la fecha ya haya pasado', function () {
    $contract = Contract::factory()->startingOn('2026-09-01')->indefinite()->create(['monthly_amount' => '1850.50', 'due_day' => 5]);

    app(GenerateContractPeriodsAction::class)->handle($contract);

    $september = $contract->periods()->where('month', '2026-09-01')->sole();

    expect($september->fee)->toBe('1850.50')
        ->and($september->total)->toBe('1850.50')
        ->and($september->paid_amount)->toBe('0.00')
        ->and($september->due_date->toDateString())->toBe('2026-09-05')
        ->and(BusinessDate::isPast($september->due_date))->toBeTrue()
        ->and($contract->periods()->where('month', '2026-10-01')->sole()->due_date->toDateString())->toBe('2026-10-05');
});

it('RF-40: el período copia el cliente del contrato', function () {
    $contract = Contract::factory()->startingOn('2026-10-01')->indefinite()->create();

    app(GenerateContractPeriodsAction::class)->handle($contract);

    expect($contract->periods()->sole()->client_id)->toBe($contract->client_id);
});

it('RF-41: registra la generación de cada período con el sistema como autor', function () {
    $contract = Contract::factory()->startingOn('2026-09-01')->indefinite()->create();

    app(GenerateContractPeriodsAction::class)->handle($contract);

    $entries = PeriodHistory::query()->orderBy('id')->get();

    expect($entries)->toHaveCount(2)
        ->and($entries->pluck('field')->unique()->all())->toBe([PeriodHistoryField::Generated])
        ->and($entries->pluck('author_id')->unique()->all())->toBe([null])
        ->and($entries[0]->old_value)->toBeNull()
        ->and($entries[0]->new_value)->toMatchArray(['month' => '2026-09-01', 'fee' => '1000.00', 'due_date' => '2026-09-10'])
        ->and($entries[0]->contract_period_id)->toBe($contract->periods()->first()->id);
});

it('RF-22: un contrato cancelado o finalizado no genera períodos', function (string $state) {
    $contract = Contract::factory()->startingOn('2026-07-01')->indefinite()->{$state}()->create();

    expect(app(GenerateContractPeriodsAction::class)->handle($contract))->toBeEmpty()
        ->and(ContractPeriod::query()->count())->toBe(0);
})->with(['cancelled', 'finished']);

it('RF-35: sigue generando períodos aunque el cliente esté inactivo o archivado', function (string $state) {
    $contract = Contract::factory()->startingOn('2026-09-01')->indefinite()->create();

    $state === 'inactive'
        ? $contract->client->forceFill(['status' => 'inactive'])->save()
        : $contract->client->delete();

    $created = app(GenerateContractPeriodsAction::class)->handle($contract->refresh());

    expect($created)->toHaveCount(2);
})->with(['inactive', 'archived']);

it('RF-36: el mes actual se cuenta en hora argentina, no en UTC', function () {
    // 01:30 UTC del 1 de noviembre son las 22:30 del 31 de octubre en Argentina.
    Carbon::setTestNow(CarbonImmutable::parse('2026-11-01 01:30:00', 'UTC'));
    $contract = Contract::factory()->startingOn('2026-10-01')->indefinite()->create();

    app(GenerateContractPeriodsAction::class)->handle($contract);

    expect(months($contract))->toBe(['2026-10']);
});

it('RF-36: crear el contrato genera los períodos de los meses ya transcurridos', function () {
    $author = User::factory()->staff()->create();
    $budget = Budget::factory()->accepted()->monthly()->create(['total' => '1200.00']);

    $contract = app(CreateContractAction::class)
        ->handle($budget, ['start_date' => '2026-07-20', 'due_day' => 10, 'term_months' => 6], $author);

    expect(months($contract))->toBe(['2026-07', '2026-08', '2026-09', '2026-10'])
        ->and($contract->periods()->first()->fee)->toBe('1200.00')
        ->and(PeriodHistory::query()->count())->toBe(4);
});
