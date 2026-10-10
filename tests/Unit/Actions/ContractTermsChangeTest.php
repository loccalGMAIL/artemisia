<?php

use App\Actions\GenerateContractPeriodsAction;
use App\Actions\UpdateContractAmountAction;
use App\Actions\UpdateContractDueDayAction;
use App\Enums\ContractHistoryField;
use App\Exceptions\InvalidContractTransitionException;
use App\Models\Contract;
use App\Models\ContractHistory;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    app()->setLocale('es');
    // 12:00 del 15 de octubre de 2026 en Argentina.
    Carbon::setTestNow(CarbonImmutable::parse('2026-10-15 15:00:00', 'UTC'));
    $this->staff = User::factory()->create();
});

afterEach(fn () => Carbon::setTestNow());

function changeAmount(Contract $contract, mixed $amount, User $actor): Contract
{
    return app(UpdateContractAmountAction::class)->handle($contract, $amount, $actor);
}

function changeDueDay(Contract $contract, mixed $day, User $actor): Contract
{
    return app(UpdateContractDueDayAction::class)->handle($contract, $day, $actor);
}

function termEntries(Contract $contract, ContractHistoryField $field)
{
    return ContractHistory::query()->where('contract_id', $contract->id)->where('field', $field)->orderBy('id')->get();
}

it('RF-30: modifica el importe mensual de un contrato vigente', function (mixed $input, string $stored) {
    $contract = Contract::factory()->create(['monthly_amount' => '1000.00']);

    $updated = changeAmount($contract, $input, $this->staff);

    expect($updated->monthly_amount)->toBe($stored)
        ->and($contract->fresh()->monthly_amount)->toBe($stored);
})->with([
    'entero' => ['1500', '1500.00'],
    'un decimal' => ['1500.5', '1500.50'],
    'dos decimales' => ['1500.55', '1500.55'],
    'número' => [1500, '1500.00'],
    'con espacios' => [' 1500.00 ', '1500.00'],
]);

it('RF-10: rechaza un importe que no es mayor que cero o tiene más de 2 decimales y dice el motivo', function (mixed $input) {
    $contract = Contract::factory()->create(['monthly_amount' => '1000.00']);

    try {
        changeAmount($contract, $input, $this->staff);
        $this->fail('Se esperaba un rechazo.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('monthly_amount')
            ->and($e->errors()['monthly_amount'][0])->toContain('importe');
    }

    expect($contract->fresh()->monthly_amount)->toBe('1000.00')
        ->and(termEntries($contract, ContractHistoryField::AmountChanged))->toHaveCount(0);
})->with([
    'cero' => '0',
    'cero con decimales' => '0.00',
    'negativo' => '-100',
    'tres decimales' => '1500.123',
    'texto' => 'mil',
    'vacío' => '',
    'nulo' => null,
    'con coma' => '1500,50',
    'demasiado grande' => '100000000000',
]);

it('RF-31: modifica el día de vencimiento de un contrato vigente', function (mixed $input, int $stored) {
    $contract = Contract::factory()->create(['due_day' => 10]);

    $updated = changeDueDay($contract, $input, $this->staff);

    expect($updated->due_day)->toBe($stored)
        ->and($contract->fresh()->due_day)->toBe($stored);
})->with([
    'primer día' => [1, 1],
    'último día' => [28, 28],
    'texto numérico' => ['15', 15],
]);

it('RF-31: rechaza un día que no está entre 1 y 28 y dice el motivo', function (mixed $input) {
    $contract = Contract::factory()->create(['due_day' => 10]);

    try {
        changeDueDay($contract, $input, $this->staff);
        $this->fail('Se esperaba un rechazo.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('due_day')
            ->and($e->errors()['due_day'][0])->toContain('1 y 28');
    }

    expect($contract->fresh()->due_day)->toBe(10)
        ->and(termEntries($contract, ContractHistoryField::DueDayChanged))->toHaveCount(0);
})->with(['cero' => 0, 'veintinueve' => 29, 'treinta y uno' => 31, 'negativo' => -3, 'decimal' => '10.5', 'texto' => 'diez', 'vacío' => '', 'nulo' => null]);

it('RF-30, RF-31: solo se modifica un contrato vigente', function (string $state) {
    $contract = Contract::factory()->{$state}()->create(['monthly_amount' => '1000.00', 'due_day' => 10]);

    expect(fn () => changeAmount($contract, '2000', $this->staff))
        ->toThrow(InvalidContractTransitionException::class, 'vigente')
        ->and(fn () => changeDueDay($contract, 20, $this->staff))
        ->toThrow(InvalidContractTransitionException::class, 'vigente');

    expect($contract->fresh()->monthly_amount)->toBe('1000.00')
        ->and($contract->fresh()->due_day)->toBe(10)
        ->and(ContractHistory::query()->where('contract_id', $contract->id)->count())->toBe(0);
})->with(['cancelled', 'finished']);

it('RF-30: se decide con el contrato como está al confirmar, no como se vio al abrir', function () {
    $contract = Contract::factory()->create();
    $stale = Contract::query()->findOrFail($contract->id);
    $contract->update(['status' => 'cancelled']);

    expect(fn () => changeAmount($stale, '2000', $this->staff))->toThrow(InvalidContractTransitionException::class);
});

it('RF-32: el cambio rige desde los períodos que se generen después y no altera los existentes', function () {
    $contract = Contract::factory()->startingOn('2026-09-01')->indefinite()->create(['monthly_amount' => '1000.00', 'due_day' => 10]);
    app(GenerateContractPeriodsAction::class)->handle($contract);

    changeAmount($contract, '1800', $this->staff);
    changeDueDay($contract->fresh(), 20, $this->staff);

    $existing = $contract->periods()->get();

    expect($existing)->toHaveCount(2)
        ->and($existing->pluck('fee')->all())->toBe(['1000.00', '1000.00'])
        ->and($existing->pluck('total')->all())->toBe(['1000.00', '1000.00'])
        ->and($existing->map(fn ($period) => $period->due_date->toDateString())->all())->toBe(['2026-09-10', '2026-10-10']);

    // Pasa un mes: el período nuevo toma los valores nuevos.
    Carbon::setTestNow(CarbonImmutable::parse('2026-11-02 15:00:00', 'UTC'));
    app(GenerateContractPeriodsAction::class)->handle($contract->fresh());

    $november = $contract->periods()->get()->last();

    expect($november->month->format('Y-m'))->toBe('2026-11')
        ->and($november->fee)->toBe('1800.00')
        ->and($november->due_date->toDateString())->toBe('2026-11-20')
        ->and($contract->periods()->count())->toBe(3);
});

it('RF-33: registra el importe anterior y el nuevo, el autor y la fecha', function () {
    $contract = Contract::factory()->create(['monthly_amount' => '1000.00']);

    changeAmount($contract, '1500.5', $this->staff);

    $entry = termEntries($contract, ContractHistoryField::AmountChanged)->sole();

    expect($entry->old_value)->toBe(['monthly_amount' => '1000.00'])
        ->and($entry->new_value)->toBe(['monthly_amount' => '1500.50'])
        ->and($entry->author_id)->toBe($this->staff->id)
        ->and($entry->created_at)->not->toBeNull();
});

it('RF-33: registra el día anterior y el nuevo, el autor y la fecha', function () {
    $contract = Contract::factory()->create(['due_day' => 10]);

    changeDueDay($contract, 20, $this->staff);

    $entry = termEntries($contract, ContractHistoryField::DueDayChanged)->sole();

    expect($entry->old_value)->toBe(['due_day' => 10])
        ->and($entry->new_value)->toBe(['due_day' => 20])
        ->and($entry->author_id)->toBe($this->staff->id);
});

it('RF-33: guardar el mismo valor no deja un asiento', function () {
    $contract = Contract::factory()->create(['monthly_amount' => '1000.00', 'due_day' => 10]);

    changeAmount($contract, '1000', $this->staff);
    changeDueDay($contract, 10, $this->staff);

    expect(ContractHistory::query()->where('contract_id', $contract->id)->count())->toBe(0);
});

it('RF-30, RF-31: cambiar el importe no toca el día y cambiar el día no toca el importe', function () {
    $contract = Contract::factory()->create(['monthly_amount' => '1000.00', 'due_day' => 10]);

    changeAmount($contract, '2000', $this->staff);
    expect($contract->fresh()->due_day)->toBe(10);

    changeDueDay($contract->fresh(), 25, $this->staff);
    expect($contract->fresh()->monthly_amount)->toBe('2000.00');
});
