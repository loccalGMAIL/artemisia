<?php

use App\Enums\ContractStatus;
use App\Filament\Staff\Resources\Contracts\Pages\ViewContract;
use App\Models\Contract;
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
});

afterEach(fn () => Carbon::setTestNow());

function contractCard(Contract $contract)
{
    return Livewire::test(ViewContract::class, ['record' => $contract->getKey()]);
}

it('RF-21: cancela un contrato vigente desde la ficha', function (string $role) {
    $this->actingAs(User::factory()->{$role}()->create());
    $contract = Contract::factory()->create();

    contractCard($contract)
        ->assertActionVisible('cancelContract')
        ->callAction('cancelContract')
        ->assertNotified('Contrato cancelado.');

    expect($contract->fresh()->status)->toBe(ContractStatus::Cancelled);
})->with(['admin', 'staff']);

it('RF-25: reactiva un contrato cancelado desde la ficha y repone sus períodos', function () {
    $contract = Contract::factory()->startingOn('2026-09-01')->indefinite()->cancelled()->create();

    contractCard($contract)
        ->assertActionVisible('reactivateContract')
        ->callAction('reactivateContract')
        ->assertNotified('Contrato reactivado.');

    expect($contract->fresh()->status)->toBe(ContractStatus::Active)
        ->and($contract->periods()->count())->toBe(2);
});

it('RF-25: un rechazo al reactivar se muestra con su motivo y el contrato sigue cancelado', function () {
    $contract = Contract::factory()->startingOn('2026-06-01')->fixedTerm(3)->cancelled()->create();

    contractCard($contract)
        ->callAction('reactivateContract')
        ->assertNotified('El plazo del contrato ya transcurrió por completo.');

    expect($contract->fresh()->status)->toBe(ContractStatus::Cancelled)
        ->and($contract->periods()->count())->toBe(0);
});

it('RF-30: modifica el importe mensual desde la ficha', function () {
    $contract = Contract::factory()->create(['monthly_amount' => '1000.00']);

    contractCard($contract)
        ->assertActionVisible('updateAmount')
        ->callAction('updateAmount', ['monthly_amount' => '1850.50'])
        ->assertHasNoActionErrors()
        ->assertNotified('Importe mensual actualizado.');

    expect($contract->fresh()->monthly_amount)->toBe('1850.50');
});

it('RF-30: el formulario del importe viene con el importe actual', function () {
    $contract = Contract::factory()->create(['monthly_amount' => '1000.00']);

    contractCard($contract)
        ->mountAction('updateAmount')
        ->assertActionDataSet(['monthly_amount' => '1000.00']);
});

it('RF-30: un importe que no es mayor que cero o tiene más de 2 decimales se rechaza en el formulario', function (string $amount) {
    $contract = Contract::factory()->create(['monthly_amount' => '1000.00']);

    contractCard($contract)
        ->callAction('updateAmount', ['monthly_amount' => $amount])
        ->assertHasActionErrors(['monthly_amount']);

    expect($contract->fresh()->monthly_amount)->toBe('1000.00');
})->with(['cero' => '0', 'tres decimales' => '10.123', 'texto' => 'mil']);

it('RF-31: modifica el día de vencimiento desde la ficha', function () {
    $contract = Contract::factory()->create(['due_day' => 10]);

    contractCard($contract)
        ->assertActionVisible('updateDueDay')
        ->callAction('updateDueDay', ['due_day' => 20])
        ->assertHasNoActionErrors()
        ->assertNotified('Día de vencimiento actualizado.');

    expect($contract->fresh()->due_day)->toBe(20);
});

it('RF-31: el formulario del día viene con el día actual', function () {
    $contract = Contract::factory()->create(['due_day' => 10]);

    contractCard($contract)
        ->mountAction('updateDueDay')
        ->assertActionDataSet(['due_day' => 10]);
});

it('RF-31: un día fuera de 1 a 28 se rechaza en el formulario', function (int $day) {
    $contract = Contract::factory()->create(['due_day' => 10]);

    contractCard($contract)
        ->callAction('updateDueDay', ['due_day' => $day])
        ->assertHasActionErrors(['due_day']);

    expect($contract->fresh()->due_day)->toBe(10);
})->with([0, 29]);

it('RF-21, RF-25, RF-30, RF-31: cada acción se ofrece solo en el estado que corresponde', function () {
    $active = Contract::factory()->create();
    $cancelled = Contract::factory()->cancelled()->create();
    $finished = Contract::factory()->finished()->create();

    contractCard($active)
        ->assertActionVisible('cancelContract')
        ->assertActionVisible('updateAmount')
        ->assertActionVisible('updateDueDay')
        ->assertActionHidden('reactivateContract');

    contractCard($cancelled)
        ->assertActionVisible('reactivateContract')
        ->assertActionHidden('cancelContract')
        ->assertActionHidden('updateAmount')
        ->assertActionHidden('updateDueDay');

    contractCard($finished)
        ->assertActionHidden('reactivateContract')
        ->assertActionHidden('cancelContract')
        ->assertActionHidden('updateAmount')
        ->assertActionHidden('updateDueDay');
});
