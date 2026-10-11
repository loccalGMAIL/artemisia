<?php

use App\Actions\RecalculatePeriodTotalsAction;
use App\Actions\VoidPaymentAction;
use App\Enums\PaymentStatus;
use App\Enums\PeriodHistoryField;
use App\Models\ContractPeriod;
use App\Models\Payment;
use App\Models\PeriodHistory;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    app()->setLocale('es');
    Carbon::setTestNow(CarbonImmutable::parse('2026-10-15 15:00:00', 'UTC'));
    $this->staff = User::factory()->create();
    $this->period = ContractPeriod::factory()->create(['fee' => '1000.00', 'total' => '1000.00']);
});

afterEach(fn () => Carbon::setTestNow());

function paymentOn(ContractPeriod $period, string $amount): Payment
{
    $payment = Payment::factory()->create(['contract_period_id' => $period->id, 'amount' => $amount]);
    app(RecalculatePeriodTotalsAction::class)->handle($period);

    return $payment;
}

function voidPayment(Payment $payment, ?string $justification, User $actor): Payment
{
    return app(VoidPaymentAction::class)->handle($payment, $justification, $actor);
}

it('RF-75, RF-77: anula un pago vigente con su justificación, su autor y su fecha', function () {
    $payment = paymentOn($this->period, '400.00');

    $voided = voidPayment($payment, '  Cargado dos veces  ', $this->staff);

    expect($voided->isVoided())->toBeTrue()
        ->and($voided->void_justification)->toBe('Cargado dos veces')
        ->and($voided->voided_by)->toBe($this->staff->id)
        ->and($voided->voided_at->toDateTimeString())->toBe('2026-10-15 15:00:00')
        ->and(Payment::query()->whereKey($payment->id)->sole()->isVoided())->toBeTrue();
});

it('RF-77, RF-68: el pago anulado deja de computarse en lo pagado y en el saldo', function () {
    $keep = paymentOn($this->period, '300.00');
    $void = paymentOn($this->period, '500.00');

    expect($this->period->fresh()->payment_status)->toBe(PaymentStatus::Partial)
        ->and($this->period->fresh()->paid_amount)->toBe('800.00');

    voidPayment($void, 'Error de importe', $this->staff);

    $period = $this->period->fresh();

    expect($period->paid_amount)->toBe('300.00')
        ->and($period->balance)->toBe('700.00')
        ->and($period->payments()->count())->toBe(2)
        ->and($period->payments()->valid()->sole()->id)->toBe($keep->id);

    voidPayment($keep->fresh(), 'Error de importe', $this->staff);

    expect($this->period->fresh()->payment_status)->toBe(PaymentStatus::Pending)
        ->and($this->period->fresh()->balance)->toBe('1000.00');
});

it('RF-76: rechaza anular sin justificación e indica que falta', function (?string $justification) {
    $payment = paymentOn($this->period, '400.00');

    try {
        voidPayment($payment, $justification, $this->staff);
        $this->fail('Se esperaba un rechazo.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('void_justification')
            ->and($e->errors()['void_justification'][0])->toContain('justificación');
    }

    expect($payment->fresh()->isVoided())->toBeFalse()
        ->and($this->period->fresh()->paid_amount)->toBe('400.00');
})->with(['nula' => null, 'vacía' => '', 'espacios' => '   ']);

it('RF-76: rechaza una justificación de más de 255 caracteres', function () {
    $payment = paymentOn($this->period, '400.00');

    expect(fn () => voidPayment($payment, str_repeat('a', 256), $this->staff))->toThrow(ValidationException::class)
        ->and($payment->fresh()->isVoided())->toBeFalse();
});

it('RF-75, RF-78: solo se anula un pago vigente: uno ya anulado no se anula ni se reactiva', function () {
    $payment = paymentOn($this->period, '400.00');
    voidPayment($payment, 'Primera anulación', $this->staff);

    expect(fn () => voidPayment($payment->fresh(), 'Segunda anulación', $this->staff))
        ->toThrow(ValidationException::class, 'anulado');

    expect($payment->fresh()->void_justification)->toBe('Primera anulación')
        ->and(PeriodHistory::query()->where('field', PeriodHistoryField::PaymentVoided)->count())->toBe(1);
});

it('RF-75: anular se decide con el pago como está al confirmar, no como se vio al abrir', function () {
    $payment = paymentOn($this->period, '400.00');
    $stale = Payment::query()->findOrFail($payment->id);

    voidPayment($payment, 'La hizo otra persona', User::factory()->create());

    expect(fn () => voidPayment($stale, 'Yo también', $this->staff))->toThrow(ValidationException::class)
        ->and($payment->fresh()->void_justification)->toBe('La hizo otra persona');
});

it('RF-78: ningún dato de un pago registrado se puede modificar', function (string $field, mixed $value) {
    $payment = paymentOn($this->period, '400.00');

    expect(fn () => $payment->update([$field => $value]))->toThrow(LogicException::class)
        ->and($payment->fresh()->getAttribute($field))->not->toBe($value);
})->with([
    'importe' => ['amount', '1.00'],
    'fecha' => ['paid_on', '2020-01-01'],
    'forma de pago' => ['method', 'transfer'],
    'referencia' => ['reference', 'Otra'],
    'período' => ['contract_period_id', 999999],
]);

it('RF-78: tampoco se modifica un pago ya anulado', function () {
    $payment = voidPayment(paymentOn($this->period, '400.00'), 'Error', $this->staff);

    expect(fn () => $payment->update(['void_justification' => 'Otra justificación']))->toThrow(LogicException::class)
        ->and(fn () => $payment->update(['voided_at' => null]))->toThrow(LogicException::class)
        ->and($payment->fresh()->isVoided())->toBeTrue();
});

it('RF-80: un pago no se elimina nunca', function () {
    $payment = paymentOn($this->period, '400.00');

    expect(fn () => $payment->delete())->toThrow(LogicException::class)
        ->and(fn () => $payment->forceDelete())->toThrow(LogicException::class)
        ->and(Payment::query()->whereKey($payment->id)->exists())->toBeTrue();
});

it('RF-79: deja un asiento con el pago anulado, la justificación, el autor y la fecha', function () {
    $payment = paymentOn($this->period, '400.00');
    $other = User::factory()->create();

    voidPayment($payment, 'Error de importe', $other);

    $entry = PeriodHistory::query()
        ->where('contract_period_id', $this->period->id)
        ->where('field', PeriodHistoryField::PaymentVoided)
        ->sole();

    expect($entry->old_value['id'])->toBe($payment->id)
        ->and($entry->old_value['amount'])->toBe('400.00')
        ->and($entry->new_value)->toBe(['id' => $payment->id, 'justification' => 'Error de importe'])
        ->and($entry->author_id)->toBe($other->id)
        ->and($entry->created_at)->not->toBeNull();
});

it('RF-79: un rechazo no deja asiento', function () {
    $payment = paymentOn($this->period, '400.00');

    try {
        voidPayment($payment, '', $this->staff);
    } catch (ValidationException) {
    }

    expect(PeriodHistory::query()->where('field', PeriodHistoryField::PaymentVoided)->count())->toBe(0);
});
