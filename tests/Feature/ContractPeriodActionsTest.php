<?php

use App\Enums\ConceptType;
use App\Filament\Staff\Resources\ContractPeriods\Pages\ViewContractPeriod;
use App\Filament\Staff\Resources\ContractPeriods\RelationManagers\ConceptsRelationManager;
use App\Models\ContractPeriod;
use App\Models\Payment;
use App\Models\PeriodConcept;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    app()->setLocale('es');
    Filament::setCurrentPanel('staff');
    $this->actor = User::factory()->staff()->create();
    $this->actingAs($this->actor);
    $this->period = ContractPeriod::factory()->create(['fee' => '1000.00', 'total' => '1000.00']);
});

function periodCard(ContractPeriod $period)
{
    return Livewire::test(ViewContractPeriod::class, ['record' => $period->getKey()]);
}

function conceptsOf(ContractPeriod $period)
{
    return Livewire::test(ConceptsRelationManager::class, ['ownerRecord' => $period, 'pageClass' => ViewContractPeriod::class]);
}

it('RF-42: agrega un extra desde la ficha del período', function (string $role) {
    $this->actingAs(User::factory()->{$role}()->create());

    periodCard($this->period)
        ->assertActionVisible('addExtra')
        ->callAction('addExtra', ['description' => 'Banner adicional', 'amount' => '250.50'])
        ->assertHasNoActionErrors()
        ->assertNotified('Extra agregado.');

    $concept = $this->period->concepts()->sole();

    expect($concept->type)->toBe(ConceptType::Extra)
        ->and($concept->description)->toBe('Banner adicional')
        ->and($concept->amount)->toBe('250.50')
        ->and($this->period->fresh()->total)->toBe('1250.50');
})->with(['admin', 'staff']);

it('RF-43: agrega un descuento con su justificación desde la ficha del período', function () {
    periodCard($this->period)
        ->assertActionVisible('addDiscount')
        ->callAction('addDiscount', ['description' => 'Bonificación por pronto pago', 'amount' => '100.00'])
        ->assertHasNoActionErrors()
        ->assertNotified('Descuento agregado.');

    $concept = $this->period->concepts()->sole();

    expect($concept->type)->toBe(ConceptType::Discount)
        ->and($concept->description)->toBe('Bonificación por pronto pago')
        ->and($this->period->fresh()->total)->toBe('900.00');
});

it('RF-43: un descuento sin justificación se rechaza en el formulario y no se agrega', function () {
    periodCard($this->period)
        ->callAction('addDiscount', ['description' => '', 'amount' => '100.00'])
        ->assertHasActionErrors(['description']);

    expect($this->period->concepts()->count())->toBe(0);
});

it('RF-42: un importe que no es mayor que cero se rechaza en el formulario', function () {
    periodCard($this->period)
        ->callAction('addExtra', ['description' => 'Banner', 'amount' => '0'])
        ->assertHasActionErrors(['amount']);

    expect($this->period->concepts()->count())->toBe(0);
});

it('RF-42: un rechazo de la Action se muestra con su motivo y no cambia el período', function () {
    periodCard($this->period)
        ->callAction('addDiscount', ['description' => 'Demasiado', 'amount' => '1000.01'])
        ->assertNotified('El total del período no puede quedar por debajo de cero.');

    expect($this->period->concepts()->count())->toBe(0)
        ->and($this->period->fresh()->total)->toBe('1000.00');
});

it('RF-42: el tope de conceptos se muestra con su motivo', function () {
    PeriodConcept::factory()->count(100)->create(['contract_period_id' => $this->period->id, 'amount' => '1.00']);

    periodCard($this->period)
        ->callAction('addExtra', ['description' => 'Uno más', 'amount' => '1.00'])
        ->assertNotified('El período ya tiene 100 conceptos cargados: no se puede agregar otro.');

    expect($this->period->concepts()->count())->toBe(100);
});

it('RF-47: quita un concepto desde la ficha del período', function () {
    $extra = PeriodConcept::factory()->create(['contract_period_id' => $this->period->id, 'amount' => '300.00']);
    $this->period->update(['total' => '1300.00']);

    conceptsOf($this->period)
        ->assertTableActionVisible('removeConcept', $extra)
        ->callTableAction('removeConcept', $extra)
        ->assertNotified('Concepto quitado.');

    expect(PeriodConcept::query()->whereKey($extra->id)->exists())->toBeFalse()
        ->and($this->period->fresh()->total)->toBe('1000.00');
});

it('RF-47: un rechazo al quitar un concepto se muestra con su motivo', function () {
    $extra = PeriodConcept::factory()->create(['contract_period_id' => $this->period->id, 'amount' => '300.00']);
    $this->period->update(['total' => '1300.00', 'paid_amount' => '1300.00']);
    Payment::factory()->create(['contract_period_id' => $this->period->id, 'amount' => '1300.00']);

    conceptsOf($this->period)
        ->callTableAction('removeConcept', $extra)
        ->assertNotified('El total del período no puede quedar por debajo de lo ya pagado ($ 1.300,00).');

    expect(PeriodConcept::query()->whereKey($extra->id)->exists())->toBeTrue();
});

it('RF-53: carga la referencia de factura desde la ficha del período', function () {
    periodCard($this->period)
        ->assertActionVisible('setInvoice')
        ->callAction('setInvoice', ['invoice_number' => '0001-00000123', 'invoice_date' => '2026-10-05'])
        ->assertHasNoActionErrors()
        ->assertNotified('Referencia de factura guardada.');

    $period = $this->period->fresh();

    expect($period->invoice_number)->toBe('0001-00000123')
        ->and($period->invoice_date->toDateString())->toBe('2026-10-05');
});

it('RF-53: el formulario de la factura viene con la referencia actual', function () {
    $period = ContractPeriod::factory()->invoiced('0001-00000100', '2026-09-30')->create();

    periodCard($period)
        ->mountAction('setInvoice')
        ->assertActionDataSet(['invoice_number' => '0001-00000100', 'invoice_date' => '2026-09-30']);
});

it('RF-53: la factura con número y sin fecha se rechaza con su motivo', function () {
    periodCard($this->period)
        ->callAction('setInvoice', ['invoice_number' => '0001-00000123', 'invoice_date' => null])
        ->assertHasActionErrors(['invoice_date']);

    expect($this->period->fresh()->invoice_number)->toBeNull();
});
