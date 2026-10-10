<?php

use App\Actions\CreateContractAction;
use App\Actions\RevertBudgetToSentAction;
use App\Actions\UpdateBudgetHeaderAction;
use App\Enums\ContractHistoryField;
use App\Enums\ContractStatus;
use App\Exceptions\BudgetAlreadyHasContractException;
use App\Exceptions\BudgetNotEligibleForContractException;
use App\Models\Budget;
use App\Models\Contract;
use App\Models\ContractHistory;
use App\Models\User;
use App\Support\BusinessDate;

beforeEach(function () {
    app()->setLocale('es');
    $this->author = User::factory()->staff()->create();
    $this->budget = Budget::factory()->accepted()->monthly()->create(['total' => '1500.00']);
});

function contractData(array $overrides = []): array
{
    return array_merge([
        'start_date' => BusinessDate::today()->toDateString(),
        'due_day' => 10,
        'term_months' => 6,
    ], $overrides);
}

it('RF-1, RF-6, RF-20: crea un contrato vigente a partir de un presupuesto aceptado de abono mensual, copiando el cliente y el importe mensual', function () {
    $contract = app(CreateContractAction::class)->handle($this->budget, contractData(), $this->author)->refresh();

    expect($contract->budget_id)->toBe($this->budget->id)
        ->and($contract->client_id)->toBe($this->budget->client_id)
        ->and($contract->monthly_amount)->toBe('1500.00')
        ->and($contract->status)->toBe(ContractStatus::Active)
        ->and($contract->due_day)->toBe(10)
        ->and($contract->term_months)->toBe(6)
        ->and($contract->created_by)->toBe($this->author->id)
        ->and($this->budget->refresh()->contract->is($contract))->toBeTrue();
});

it('RF-33: registra el alta del contrato en el historial con su autor', function () {
    $contract = app(CreateContractAction::class)->handle($this->budget, contractData(), $this->author);

    $entry = ContractHistory::query()->where('contract_id', $contract->id)->sole();

    expect($entry->field)->toBe(ContractHistoryField::Created)
        ->and($entry->old_value)->toBeNull()
        ->and($entry->new_value)->toMatchArray(['status' => 'active', 'monthly_amount' => '1500.00', 'due_day' => 10, 'term_months' => 6])
        ->and($entry->author_id)->toBe($this->author->id);
});

it('RF-2: rechaza con motivo un presupuesto que no está aceptado', function (string $state) {
    $budget = Budget::factory()->{$state}()->monthly()->create(['total' => '1500.00']);

    try {
        app(CreateContractAction::class)->handle($budget, contractData(), $this->author);
        $this->fail('Debió rechazar el presupuesto.');
    } catch (BudgetNotEligibleForContractException $exception) {
        expect($exception->getMessage())->toContain('aceptado');
    }

    expect(Contract::withTrashed()->count())->toBe(0)
        ->and(ContractHistory::query()->count())->toBe(0);
})->with(['draft', 'sent', 'rejected']);

it('RF-3: rechaza con motivo un presupuesto de pago único', function () {
    $budget = Budget::factory()->accepted()->create(['total' => '1500.00']);

    try {
        app(CreateContractAction::class)->handle($budget, contractData(), $this->author);
        $this->fail('Debió rechazar el presupuesto.');
    } catch (BudgetNotEligibleForContractException $exception) {
        expect($exception->getMessage())->toContain('abono mensual');
    }

    expect(Contract::withTrashed()->count())->toBe(0);
});

it('RF-4: rechaza con motivo un presupuesto cuyo importe mensual es cero', function () {
    $budget = Budget::factory()->accepted()->monthly()->create(['total' => '0.00']);

    try {
        app(CreateContractAction::class)->handle($budget, contractData(), $this->author);
        $this->fail('Debió rechazar el presupuesto.');
    } catch (BudgetNotEligibleForContractException $exception) {
        expect($exception->getMessage())->toContain('importe');
    }

    expect(Contract::withTrashed()->count())->toBe(0);
});

it('RF-5: no crea un segundo contrato para el mismo presupuesto y muestra el existente', function () {
    $existing = app(CreateContractAction::class)->handle($this->budget, contractData(), $this->author);

    try {
        app(CreateContractAction::class)->handle($this->budget, contractData(['due_day' => 20]), $this->author);
        $this->fail('Debió rechazar el alta.');
    } catch (BudgetAlreadyHasContractException $exception) {
        expect($exception->contract->is($existing))->toBeTrue()
            ->and($exception->getMessage())->toContain("#{$existing->id}");
    }

    expect(Contract::withTrashed()->count())->toBe(1);
});

it('RF-18: el contrato no cambia si el presupuesto vuelve a enviado o se modifica después', function () {
    $contract = app(CreateContractAction::class)->handle($this->budget, contractData(), $this->author)->refresh();
    $before = $contract->only(['client_id', 'monthly_amount', 'status', 'due_day', 'term_months']);

    app(RevertBudgetToSentAction::class)->handle($this->budget->refresh(), $this->author);
    app(UpdateBudgetHeaderAction::class)->handle($this->budget->refresh(), ['title' => 'Otro título'], $this->author);
    $this->budget->forceFill(['total' => '9999.00'])->save();

    expect($contract->refresh()->only(['client_id', 'monthly_amount', 'status', 'due_day', 'term_months']))->toBe($before)
        ->and(ContractHistory::query()->count())->toBe(1);
});
