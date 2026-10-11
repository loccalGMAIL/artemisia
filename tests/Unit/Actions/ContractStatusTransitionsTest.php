<?php

use App\Actions\CancelContractAction;
use App\Actions\GenerateContractPeriodsAction;
use App\Actions\ReactivateContractAction;
use App\Enums\ContractHistoryField;
use App\Enums\ContractStatus;
use App\Enums\PeriodHistoryField;
use App\Exceptions\ContractTermElapsedException;
use App\Exceptions\InvalidContractTransitionException;
use App\Models\Contract;
use App\Models\ContractHistory;
use App\Models\ContractPeriod;
use App\Models\Payment;
use App\Models\PeriodConcept;
use App\Models\PeriodHistory;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

beforeEach(function () {
    app()->setLocale('es');
    // 12:00 del 15 de octubre de 2026 en Argentina.
    Carbon::setTestNow(CarbonImmutable::parse('2026-10-15 15:00:00', 'UTC'));
    $this->staff = User::factory()->create();
});

afterEach(fn () => Carbon::setTestNow());

function cancel(Contract $contract, User $actor): Contract
{
    return app(CancelContractAction::class)->handle($contract, $actor);
}

function reactivate(Contract $contract, User $actor): Contract
{
    return app(ReactivateContractAction::class)->handle($contract, $actor);
}

function statusEntries(Contract $contract)
{
    return ContractHistory::query()
        ->where('contract_id', $contract->id)
        ->where('field', ContractHistoryField::StatusChanged)
        ->orderBy('id')
        ->get();
}

it('RF-21: cancela un contrato vigente', function () {
    $contract = Contract::factory()->create();

    $cancelled = cancel($contract, $this->staff);

    expect($cancelled->status)->toBe(ContractStatus::Cancelled)
        ->and($contract->fresh()->status)->toBe(ContractStatus::Cancelled);
});

it('RF-21: solo se cancela desde vigente', function (string $state) {
    $contract = Contract::factory()->{$state}()->create();

    expect(fn () => cancel($contract, $this->staff))->toThrow(InvalidContractTransitionException::class)
        ->and(statusEntries($contract))->toHaveCount(0);
})->with(['cancelled', 'finished']);

it('RF-22: un contrato cancelado deja de generar períodos', function () {
    $contract = Contract::factory()->startingOn('2026-08-01')->indefinite()->create();
    app(GenerateContractPeriodsAction::class)->handle($contract);
    cancel($contract, $this->staff);

    Carbon::setTestNow(CarbonImmutable::parse('2026-12-15 15:00:00', 'UTC'));

    expect(app(GenerateContractPeriodsAction::class)->handle($contract->fresh()))->toHaveCount(0)
        ->and($contract->periods()->count())->toBe(3);
});

it('RF-23: cancelar conserva los períodos con sus conceptos, su saldo y sus pagos', function () {
    $contract = Contract::factory()->startingOn('2026-09-01')->indefinite()->create();
    app(GenerateContractPeriodsAction::class)->handle($contract);
    $period = $contract->periods()->first();
    $concept = PeriodConcept::factory()->create(['contract_period_id' => $period->id, 'amount' => '100.00']);
    $payment = Payment::factory()->create(['contract_period_id' => $period->id, 'amount' => '300.00']);
    $period->update(['total' => '1100.00', 'paid_amount' => '300.00']);

    cancel($contract, $this->staff);

    expect($contract->periods()->count())->toBe(2)
        ->and($period->fresh()->total)->toBe('1100.00')
        ->and($period->fresh()->paid_amount)->toBe('300.00')
        ->and($period->fresh()->balance)->toBe('800.00')
        ->and(PeriodConcept::query()->whereKey($concept->id)->exists())->toBeTrue()
        ->and(Payment::query()->whereKey($payment->id)->exists())->toBeTrue();
});

it('RF-25, RF-26: reactiva un contrato cancelado y repone los períodos que faltan hasta el mes actual', function () {
    $contract = Contract::factory()->startingOn('2026-07-01')->indefinite()->create();
    ContractPeriod::factory()->create(['contract_id' => $contract->id, 'month' => '2026-07-01']);
    ContractPeriod::factory()->create(['contract_id' => $contract->id, 'month' => '2026-08-01']);
    $contract->update(['status' => ContractStatus::Cancelled]);

    $reactivated = reactivate($contract->fresh(), $this->staff);

    expect($reactivated->status)->toBe(ContractStatus::Active)
        ->and($contract->periods()->get()->map(fn ($period) => $period->month->format('Y-m'))->all())
        ->toBe(['2026-07', '2026-08', '2026-09', '2026-10']);
});

it('RF-26: al reponer no pasa del último mes del plazo', function () {
    $contract = Contract::factory()->startingOn('2026-08-01')->fixedTerm(3)->create(['status' => ContractStatus::Cancelled]);

    reactivate($contract, $this->staff);

    expect($contract->periods()->get()->map(fn ($period) => $period->month->format('Y-m'))->all())
        ->toBe(['2026-08', '2026-09', '2026-10']);
});

it('RF-26: los períodos repuestos toman el importe vigente del contrato y nacen con el sistema como autor', function () {
    $contract = Contract::factory()->startingOn('2026-09-01')->indefinite()->create(['monthly_amount' => '2500.00', 'status' => ContractStatus::Cancelled]);

    reactivate($contract, $this->staff);

    $periods = $contract->periods()->get();

    expect($periods)->toHaveCount(2)
        ->and($periods->every(fn ($period) => $period->fee === '2500.00'))->toBeTrue()
        ->and(PeriodHistory::query()->where('field', PeriodHistoryField::Generated)->whereNull('author_id')->count())->toBe(2);
});

it('RF-27: rechaza reactivar un plazo fijo que ya transcurrió por completo y dice el motivo', function () {
    $contract = Contract::factory()->startingOn('2026-06-01')->fixedTerm(3)->create(['status' => ContractStatus::Cancelled]);

    expect(fn () => reactivate($contract, $this->staff))
        ->toThrow(ContractTermElapsedException::class, 'plazo')
        ->and($contract->fresh()->status)->toBe(ContractStatus::Cancelled)
        ->and($contract->periods()->count())->toBe(0)
        ->and(statusEntries($contract))->toHaveCount(0);
});

it('RF-27: durante el último mes del plazo todavía se puede reactivar, y al empezar el siguiente ya no', function () {
    $contract = Contract::factory()->startingOn('2026-08-01')->fixedTerm(3)->create(['status' => ContractStatus::Cancelled]);

    // 23:59 del 31 de octubre en Argentina.
    Carbon::setTestNow(CarbonImmutable::parse('2026-11-01 02:59:00', 'UTC'));
    reactivate($contract, $this->staff);
    expect($contract->fresh()->status)->toBe(ContractStatus::Active);

    $other = Contract::factory()->startingOn('2026-08-01')->fixedTerm(3)->create(['status' => ContractStatus::Cancelled]);

    // 00:00 del 1 de noviembre en Argentina.
    Carbon::setTestNow(CarbonImmutable::parse('2026-11-01 03:00:00', 'UTC'));
    expect(fn () => reactivate($other, $this->staff))->toThrow(ContractTermElapsedException::class);
});

it('RF-28: no reactiva un contrato finalizado', function () {
    $contract = Contract::factory()->finished()->create();

    expect(fn () => reactivate($contract, $this->staff))->toThrow(InvalidContractTransitionException::class)
        ->and($contract->fresh()->status)->toBe(ContractStatus::Finished)
        ->and(statusEntries($contract))->toHaveCount(0);
});

it('RF-25: no reactiva un contrato que ya está vigente', function () {
    $contract = Contract::factory()->create();

    expect(fn () => reactivate($contract, $this->staff))->toThrow(InvalidContractTransitionException::class);
});

it('RF-33: cada cambio de estado deja un asiento con el valor anterior, el nuevo, el autor y la fecha', function () {
    $contract = Contract::factory()->indefinite()->create();
    $other = User::factory()->create();

    cancel($contract, $this->staff);
    reactivate($contract->fresh(), $other);

    $entries = statusEntries($contract);

    expect($entries)->toHaveCount(2)
        ->and($entries[0]->old_value)->toBe(['status' => 'active'])
        ->and($entries[0]->new_value)->toBe(['status' => 'cancelled'])
        ->and($entries[0]->author_id)->toBe($this->staff->id)
        ->and($entries[0]->created_at)->not->toBeNull()
        ->and($entries[1]->old_value)->toBe(['status' => 'cancelled'])
        ->and($entries[1]->new_value)->toBe(['status' => 'active'])
        ->and($entries[1]->author_id)->toBe($other->id);
});

it('RF-33: un rechazo no deja asiento', function () {
    $contract = Contract::factory()->finished()->create();

    try {
        reactivate($contract, $this->staff);
    } catch (InvalidContractTransitionException) {
    }

    expect(statusEntries($contract))->toHaveCount(0);
});

it('RF-21, RF-25: el cambio de estado se decide con el contrato como está al confirmar', function () {
    $contract = Contract::factory()->create();
    $stale = Contract::query()->findOrFail($contract->id);

    cancel($contract, $this->staff);

    expect(fn () => cancel($stale, User::factory()->create()))->toThrow(InvalidContractTransitionException::class)
        ->and(statusEntries($contract))->toHaveCount(1);
});
