<?php

use App\Enums\ConceptType;
use App\Enums\ContractHistoryField;
use App\Enums\ContractStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PeriodHistoryField;
use App\Models\Budget;
use App\Models\Client;
use App\Models\Contract;
use App\Models\ContractHistory;
use App\Models\ContractPeriod;
use App\Models\Payment;
use App\Models\PeriodConcept;
use App\Models\PeriodHistory;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;

it('RF-19: el contrato tiene exactamente un estado y nace vigente', function () {
    $contract = Contract::factory()->create()->refresh();

    expect($contract->status)->toBe(ContractStatus::Active)
        ->and(ContractStatus::cases())->toHaveCount(3)
        ->and(Contract::factory()->cancelled()->create()->status)->toBe(ContractStatus::Cancelled)
        ->and(Contract::factory()->finished()->create()->status)->toBe(ContractStatus::Finished);
});

it('RF-6, RF-8: el contrato carga su presupuesto, su cliente y su plazo, fijo o indefinido', function () {
    $fixed = Contract::factory()->fixedTerm(6)->create()->refresh();
    $indefinite = Contract::factory()->indefinite()->create()->refresh();

    expect($fixed->budget)->toBeInstanceOf(Budget::class)
        ->and($fixed->client)->toBeInstanceOf(Client::class)
        ->and($fixed->creator)->toBeInstanceOf(User::class)
        ->and($fixed->term_months)->toBe(6)
        ->and($fixed->isIndefinite())->toBeFalse()
        ->and($indefinite->term_months)->toBeNull()
        ->and($indefinite->isIndefinite())->toBeTrue()
        ->and($fixed->monthly_amount)->toBe('1000.00')
        ->and($fixed->start_date)->toBeInstanceOf(CarbonInterface::class);
});

it('RF-6: el presupuesto, el cliente y sus pagos conocen el contrato', function () {
    $contract = Contract::factory()->create();
    $period = ContractPeriod::factory()->for($contract)->create();
    $payment = Payment::factory()->for($period)->create();

    expect($contract->budget->contract->is($contract))->toBeTrue()
        ->and($contract->client->contracts->pluck('id')->all())->toBe([$contract->id])
        ->and($contract->client->payments->pluck('id')->all())->toBe([$payment->id])
        ->and($period->payments->first()->is($payment))->toBeTrue();
});

it('RF-5: un presupuesto admite un solo contrato', function () {
    $contract = Contract::factory()->create();

    expect(fn () => Contract::factory()->create(['budget_id' => $contract->budget_id]))->toThrow(QueryException::class);
});

it('RF-38: un contrato tiene a lo sumo un período por mes', function () {
    $period = ContractPeriod::factory()->create(['month' => '2026-10-01']);

    expect(fn () => ContractPeriod::factory()->for($period->contract)->create(['month' => '2026-10-01']))
        ->toThrow(QueryException::class);

    expect(ContractPeriod::factory()->for($period->contract)->create(['month' => '2026-11-01']))->toBeInstanceOf(ContractPeriod::class);
});

it('RF-40: el período carga su contrato, su cliente y sus conceptos', function () {
    $period = ContractPeriod::factory()->create()->refresh();
    PeriodConcept::factory()->for($period)->create();
    PeriodConcept::factory()->discount()->for($period)->create();

    expect($period->contract)->toBeInstanceOf(Contract::class)
        ->and($period->client_id)->toBe($period->contract->client_id)
        ->and($period->concepts)->toHaveCount(2)
        ->and($period->concepts->pluck('type')->all())->toBe([ConceptType::Extra, ConceptType::Discount])
        ->and($period->paid_amount)->toBe('0.00')
        ->and($period->month->day)->toBe(1);
});

it('RF-58: el pago tiene una forma de pago y se computa mientras no esté anulado', function () {
    $period = ContractPeriod::factory()->create();
    $valid = Payment::factory()->for($period)->create();
    Payment::factory()->voided()->for($period)->create();

    expect($valid->refresh()->method)->toBe(PaymentMethod::Cash)
        ->and(PaymentMethod::cases())->toHaveCount(3)
        ->and(Payment::query()->valid()->pluck('id')->all())->toBe([$valid->id])
        ->and($period->payments()->count())->toBe(2)
        ->and(Payment::query()->voided()->count())->toBe(1);
});

it('RF-70: el estado de pago tiene tres valores', function () {
    expect(PaymentStatus::cases())->toHaveCount(3);
});

it('RF-33, RF-41: el historial admite autor nulo, que es el sistema', function () {
    $contractEntry = ContractHistory::factory()->system()->create()->refresh();
    $periodEntry = PeriodHistory::factory()->system()->create()->refresh();

    expect($contractEntry->author_id)->toBeNull()
        ->and($contractEntry->author)->toBeNull()
        ->and($contractEntry->field)->toBe(ContractHistoryField::Created)
        ->and($periodEntry->author)->toBeNull()
        ->and($periodEntry->field)->toBe(PeriodHistoryField::Generated)
        ->and(ContractHistory::factory()->create()->author)->toBeInstanceOf(User::class);
});

it('RNF-6: un asiento del historial no se modifica ni se elimina', function (string $model) {
    $entry = $model::factory()->create();

    expect(fn () => $entry->update(['new_value' => ['x' => 1]]))->toThrow(LogicException::class)
        ->and(fn () => $entry->delete())->toThrow(LogicException::class);
})->with([ContractHistory::class, PeriodHistory::class]);

it('RF-34, RF-80: un contrato y un pago no se eliminan físicamente, y un período no se elimina', function () {
    $contract = Contract::factory()->create();
    $period = ContractPeriod::factory()->for($contract)->create();
    $payment = Payment::factory()->for($period)->create();

    expect(fn () => $contract->forceDelete())->toThrow(LogicException::class)
        ->and(fn () => $payment->forceDelete())->toThrow(LogicException::class)
        ->and(fn () => $period->delete())->toThrow(LogicException::class)
        ->and(Contract::withTrashed()->count())->toBe(1)
        ->and(Payment::withTrashed()->count())->toBe(1)
        ->and(ContractPeriod::query()->count())->toBe(1);
});

it('RF-48: el concepto cargado muestra su tipo, su descripción y su importe', function () {
    $extra = PeriodConcept::factory()->create(['description' => 'Banner extra', 'amount' => '250.50'])->refresh();

    expect($extra->type)->toBe(ConceptType::Extra)
        ->and($extra->description)->toBe('Banner extra')
        ->and($extra->amount)->toBe('250.50')
        ->and($extra->period)->toBeInstanceOf(ContractPeriod::class)
        ->and($extra->creator)->toBeInstanceOf(User::class);
});
