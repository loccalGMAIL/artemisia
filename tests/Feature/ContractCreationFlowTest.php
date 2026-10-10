<?php

use App\Filament\Staff\Resources\Budgets\Pages\ViewBudget;
use App\Models\Budget;
use App\Models\Contract;
use App\Models\ContractPeriod;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    app()->setLocale('es');
    Filament::setCurrentPanel('staff');
    // 12:00 del 15 de octubre de 2026 en Argentina.
    Carbon::setTestNow(CarbonImmutable::parse('2026-10-15 15:00:00', 'UTC'));
    $this->actor = User::factory()->staff()->create();
    $this->actingAs($this->actor);
    $this->budget = Budget::factory()->accepted()->monthly()->create(['total' => '1500.00']);
});

afterEach(fn () => Carbon::setTestNow());

function budgetCard(Budget $budget)
{
    return Livewire::test(ViewBudget::class, ['record' => $budget->getKey()]);
}

function contractForm(array $overrides = []): array
{
    return array_merge(['start_date' => '2026-09-01', 'due_day' => 10, 'indefinite' => false, 'term_months' => 6], $overrides);
}

it('RF-1, RF-6: el staff crea el contrato desde la ficha de un presupuesto aceptado de abono mensual', function (string $role) {
    $this->actingAs(User::factory()->{$role}()->create());

    budgetCard($this->budget)
        ->assertActionVisible('createContract')
        ->callAction('createContract', contractForm())
        ->assertHasNoActionErrors()
        ->assertNotified();

    $contract = Contract::query()->sole();

    expect($contract->budget_id)->toBe($this->budget->id)
        ->and($contract->client_id)->toBe($this->budget->client_id)
        ->and($contract->monthly_amount)->toBe('1500.00')
        ->and($contract->due_day)->toBe(10)
        ->and($contract->term_months)->toBe(6)
        ->and(ContractPeriod::query()->count())->toBe(2);
})->with(['admin', 'staff']);

it('RF-8: crea un contrato indefinido', function () {
    budgetCard($this->budget)
        ->callAction('createContract', contractForm(['indefinite' => true, 'term_months' => null]))
        ->assertHasNoActionErrors();

    expect(Contract::query()->sole()->isIndefinite())->toBeTrue();
});

it('RF-13, RF-14, RF-16: el formulario viene precargado con las bases del contrato anterior del cliente y se puede modificar', function () {
    Contract::factory()
        ->for(Budget::factory()->accepted()->monthly()->create(['client_id' => $this->budget->client_id]), 'budget')
        ->create(['start_date' => '2026-01-01', 'due_day' => 20, 'term_months' => 6, 'term_end_month' => '2026-06-01', 'status' => 'finished']);

    budgetCard($this->budget)
        ->mountAction('createContract')
        ->assertActionDataSet(fn (array $state): bool => $state['due_day'] === 20
            && $state['term_months'] === 6
            && $state['indefinite'] === false
            && $state['start_date'] === '2026-07-01');

    budgetCard($this->budget)
        ->callAction('createContract', contractForm(['start_date' => '2026-10-01', 'due_day' => 25, 'term_months' => 3]))
        ->assertHasNoActionErrors();

    $created = Contract::query()->where('budget_id', $this->budget->id)->sole();

    expect($created->due_day)->toBe(25)
        ->and($created->term_months)->toBe(3);
});

it('RF-13: un cliente sin contratos anteriores ve el formulario sin valores propuestos', function () {
    budgetCard($this->budget)
        ->mountAction('createContract')
        ->assertActionDataSet(fn (array $state): bool => $state['due_day'] === null
            && $state['term_months'] === null
            && $state['start_date'] === null);
});

it('RF-15: si el contrato anterior es indefinido se propone indefinido y no se propone fecha de inicio', function () {
    Contract::factory()
        ->for(Budget::factory()->accepted()->monthly()->create(['client_id' => $this->budget->client_id]), 'budget')
        ->indefinite()
        ->create(['start_date' => '2026-02-01', 'due_day' => 12]);

    budgetCard($this->budget)
        ->mountAction('createContract')
        ->assertActionDataSet(fn (array $state): bool => $state['indefinite'] === true
            && $state['start_date'] === null
            && $state['due_day'] === 12);
});

it('RF-2, RF-3, RF-4: sobre un presupuesto que no es elegible se muestra el motivo y no se crea nada', function (string $kind, string $message) {
    $budget = match ($kind) {
        'borrador' => Budget::factory()->draft()->monthly()->create(['total' => '1500.00']),
        'pago único' => Budget::factory()->accepted()->create(['total' => '1500.00']),
        'total cero' => Budget::factory()->accepted()->monthly()->create(['total' => '0.00']),
    };

    budgetCard($budget)
        ->callAction('createContract', contractForm())
        ->assertNotified($message);

    expect(Contract::withTrashed()->count())->toBe(0);
})->with([
    'borrador' => ['borrador', 'Solo se puede crear un contrato a partir de un presupuesto aceptado.'],
    'pago único' => ['pago único', 'Solo se puede crear un contrato a partir de un presupuesto de abono mensual.'],
    'total cero' => ['total cero', 'El presupuesto tiene un importe mensual de cero: no hay nada que cobrar.'],
]);

it('RF-5: si el presupuesto ya tiene un contrato se muestra el existente y no se crea otro', function () {
    $existing = Contract::factory()->create(['budget_id' => $this->budget->id, 'start_date' => '2026-10-01']);

    budgetCard($this->budget)
        ->callAction('createContract', contractForm())
        ->assertNotified("El presupuesto ya tiene el contrato #{$existing->id}.");

    expect(Contract::withTrashed()->count())->toBe(1);
});

it('RF-9: un día de vencimiento fuera de 1 a 28 se informa en el formulario', function () {
    budgetCard($this->budget)
        ->callAction('createContract', contractForm(['due_day' => 31]))
        ->assertHasActionErrors(['due_day']);

    expect(Contract::withTrashed()->count())->toBe(0);
});

it('RF-7, RF-8: la fecha de inicio y el plazo son obligatorios', function () {
    budgetCard($this->budget)
        ->callAction('createContract', contractForm(['start_date' => null, 'term_months' => null]))
        ->assertHasActionErrors(['start_date', 'term_months']);

    expect(Contract::withTrashed()->count())->toBe(0);
});

it('RF-12: un plazo fijo que ya transcurrió por completo se rechaza con el motivo', function () {
    budgetCard($this->budget)
        ->callAction('createContract', contractForm(['start_date' => '2026-01-10', 'term_months' => 3]))
        ->assertNotified('El plazo del contrato ya transcurrió por completo.');

    expect(Contract::withTrashed()->count())->toBe(0);
});
