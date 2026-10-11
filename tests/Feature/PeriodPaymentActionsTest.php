<?php

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Filament\Staff\Resources\ContractPeriods\Pages\ViewContractPeriod;
use App\Filament\Staff\Resources\ContractPeriods\RelationManagers\PaymentsRelationManager;
use App\Models\ContractPeriod;
use App\Models\Payment;
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
    $this->period = ContractPeriod::factory()->create(['fee' => '1000.00', 'total' => '1000.00']);
});

afterEach(fn () => Carbon::setTestNow());

function paymentCard(ContractPeriod $period)
{
    return Livewire::test(ViewContractPeriod::class, ['record' => $period->getKey()]);
}

function paymentsOf(ContractPeriod $period)
{
    return Livewire::test(PaymentsRelationManager::class, ['ownerRecord' => $period, 'pageClass' => ViewContractPeriod::class]);
}

function paymentForm(array $overrides = []): array
{
    return ['paid_on' => '2026-10-14', 'amount' => '400.00', 'method' => 'transfer', 'reference' => 'Transf. 998877', ...$overrides];
}

it('RF-58: registra un pago desde la ficha del período', function (string $role) {
    $this->actingAs(User::factory()->{$role}()->create());

    paymentCard($this->period)
        ->assertActionVisible('registerPayment')
        ->callAction('registerPayment', paymentForm())
        ->assertHasNoActionErrors()
        ->assertNotified('Pago registrado.');

    $payment = $this->period->payments()->sole();

    expect($payment->amount)->toBe('400.00')
        ->and($payment->paid_on->toDateString())->toBe('2026-10-14')
        ->and($payment->method)->toBe(PaymentMethod::Transfer)
        ->and($payment->reference)->toBe('Transf. 998877');
})->with(['admin', 'staff']);

it('RF-58: el saldo y el estado de pago cambian con cada pago', function () {
    paymentCard($this->period)->callAction('registerPayment', paymentForm(['amount' => '400.00']));

    expect($this->period->fresh()->balance)->toBe('600.00')
        ->and($this->period->fresh()->payment_status)->toBe(PaymentStatus::Partial);

    paymentCard($this->period)->callAction('registerPayment', paymentForm(['amount' => '600.00']));

    expect($this->period->fresh()->balance)->toBe('0.00')
        ->and($this->period->fresh()->payment_status)->toBe(PaymentStatus::Paid);
});

it('RF-58: el formulario del pago viene con la fecha de hoy', function () {
    paymentCard($this->period)
        ->mountAction('registerPayment')
        ->assertActionDataSet(['paid_on' => '2026-10-15']);
});

it('RF-63, RF-66: un importe en cero o una fecha futura se rechazan en el formulario', function () {
    paymentCard($this->period)
        ->callAction('registerPayment', paymentForm(['amount' => '0']))
        ->assertHasActionErrors(['amount']);

    paymentCard($this->period)
        ->callAction('registerPayment', paymentForm(['paid_on' => '2026-10-16']))
        ->assertHasActionErrors(['paid_on']);

    expect($this->period->payments()->count())->toBe(0);
});

it('RF-64: un pago que supera el saldo se rechaza con su motivo y el saldo', function () {
    paymentCard($this->period)
        ->callAction('registerPayment', paymentForm(['amount' => '1000.01']))
        ->assertNotified('El importe supera el saldo del período, que es $ 1.000,00.');

    expect($this->period->payments()->count())->toBe(0)
        ->and($this->period->fresh()->paid_amount)->toBe('0.00');
});

it('RF-62: el tope de pagos vigentes se muestra con su motivo', function () {
    Payment::factory()->count(100)->create(['contract_period_id' => $this->period->id, 'amount' => '1.00']);

    paymentCard($this->period)
        ->callAction('registerPayment', paymentForm(['amount' => '1.00']))
        ->assertNotified('El período ya tiene 100 pagos vigentes: no se puede registrar otro.');
});

it('RF-75: anula un pago vigente con su justificación desde la ficha del período', function () {
    $payment = Payment::factory()->create(['contract_period_id' => $this->period->id, 'amount' => '400.00']);
    $this->period->update(['paid_amount' => '400.00']);

    paymentsOf($this->period)
        ->assertTableActionVisible('voidPayment', $payment)
        ->callTableAction('voidPayment', $payment, ['void_justification' => 'Cargado dos veces'])
        ->assertHasNoTableActionErrors()
        ->assertNotified('Pago anulado.');

    $voided = $payment->fresh();

    expect($voided->isVoided())->toBeTrue()
        ->and($voided->void_justification)->toBe('Cargado dos veces')
        ->and($voided->voided_by)->toBe($this->actor->id)
        ->and($this->period->fresh()->paid_amount)->toBe('0.00')
        ->and($this->period->fresh()->payment_status)->toBe(PaymentStatus::Pending);
});

it('RF-76: anular sin justificación se rechaza en el formulario y el pago sigue vigente', function () {
    $payment = Payment::factory()->create(['contract_period_id' => $this->period->id, 'amount' => '400.00']);

    paymentsOf($this->period)
        ->callTableAction('voidPayment', $payment, ['void_justification' => ''])
        ->assertHasTableActionErrors(['void_justification']);

    expect($payment->fresh()->isVoided())->toBeFalse();
});

it('RF-75: un pago anulado no ofrece volver a anularlo', function () {
    $voided = Payment::factory()->voided('Error')->create(['contract_period_id' => $this->period->id]);

    paymentsOf($this->period)->assertTableActionHidden('voidPayment', $voided);
});
