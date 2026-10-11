<?php

use App\Actions\RegisterPaymentAction;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PeriodHistoryField;
use App\Exceptions\PaymentExceedsBalanceException;
use App\Exceptions\PeriodLimitExceededException;
use App\Models\Client;
use App\Models\Contract;
use App\Models\ContractPeriod;
use App\Models\Payment;
use App\Models\PeriodHistory;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    app()->setLocale('es');
    // 12:00 del 15 de octubre de 2026 en Argentina.
    Carbon::setTestNow(CarbonImmutable::parse('2026-10-15 15:00:00', 'UTC'));
    $this->staff = User::factory()->create();
    $this->period = ContractPeriod::factory()->create(['fee' => '1000.00', 'total' => '1000.00']);
});

afterEach(fn () => Carbon::setTestNow());

function pay(ContractPeriod $period, array $data, User $actor): Payment
{
    return app(RegisterPaymentAction::class)->handle($period, [
        'paid_on' => '2026-10-15',
        'amount' => '100.00',
        'method' => 'cash',
        ...$data,
    ], $actor);
}

it('RF-58, RF-59: registra un pago con fecha, importe y forma de pago', function (string $method, PaymentMethod $expected) {
    $payment = pay($this->period, ['paid_on' => '2026-10-10', 'amount' => '250.50', 'method' => $method], $this->staff);

    expect($payment->paid_on->toDateString())->toBe('2026-10-10')
        ->and($payment->amount)->toBe('250.50')
        ->and($payment->method)->toBe($expected)
        ->and($payment->created_by)->toBe($this->staff->id)
        ->and($payment->isVoided())->toBeFalse();
})->with([
    'efectivo' => ['cash', PaymentMethod::Cash],
    'transferencia' => ['transfer', PaymentMethod::Transfer],
    'otro' => ['other', PaymentMethod::Other],
]);

it('RF-59: rechaza una forma de pago que no es efectivo, transferencia ni otro', function (mixed $method) {
    expect(fn () => pay($this->period, ['method' => $method], $this->staff))->toThrow(ValidationException::class)
        ->and($this->period->payments()->count())->toBe(0);
})->with(['cheque' => 'cheque', 'vacía' => '', 'nula' => null]);

it('RF-60: la referencia es opcional', function () {
    $without = pay($this->period, ['reference' => null], $this->staff);
    $with = pay($this->period, ['reference' => '  Transf. 998877  '], $this->staff);

    expect($without->reference)->toBeNull()
        ->and($with->reference)->toBe('Transf. 998877');
});

it('RF-60: rechaza una referencia de más de 100 caracteres', function () {
    expect(fn () => pay($this->period, ['reference' => str_repeat('a', 101)], $this->staff))
        ->toThrow(ValidationException::class);
});

it('RF-61, RF-68: admite varios pagos sobre un período y lo pagado es su suma', function () {
    pay($this->period, ['amount' => '300.00'], $this->staff);
    pay($this->period, ['amount' => '200.25'], $this->staff);

    $period = $this->period->fresh();

    expect($period->payments()->count())->toBe(2)
        ->and($period->paid_amount)->toBe('500.25')
        ->and($period->balance)->toBe('499.75')
        ->and($period->payment_status)->toBe(PaymentStatus::Partial);
});

it('RF-61: el pago que completa el período lo deja pagado', function () {
    pay($this->period, ['amount' => '400.00'], $this->staff);
    pay($this->period, ['amount' => '600.00'], $this->staff);

    expect($this->period->fresh()->payment_status)->toBe(PaymentStatus::Paid)
        ->and($this->period->fresh()->balance)->toBe('0.00');
});

it('RF-63: el importe tiene que ser mayor que cero', function (mixed $amount) {
    try {
        pay($this->period, ['amount' => $amount], $this->staff);
        $this->fail('Se esperaba un rechazo.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('amount');
    }

    expect($this->period->payments()->count())->toBe(0);
})->with(['cero' => '0', 'negativo' => '-10', 'texto' => 'abc', 'vacío' => '', 'nulo' => null, 'menos de un centavo' => '0.001']);

it('RF-64: rechaza un pago que supera el saldo e indica el saldo', function () {
    pay($this->period, ['amount' => '600.00'], $this->staff);

    expect(fn () => pay($this->period->fresh(), ['amount' => '400.01'], $this->staff))
        ->toThrow(PaymentExceedsBalanceException::class, '$ 400,00')
        ->and($this->period->payments()->count())->toBe(1)
        ->and($this->period->fresh()->paid_amount)->toBe('600.00');
});

it('RF-64: un pago por el saldo exacto se admite', function () {
    pay($this->period, ['amount' => '1000.00'], $this->staff);

    expect($this->period->fresh()->balance)->toBe('0.00');
});

it('RF-64: un período sin saldo no admite otro pago', function () {
    pay($this->period, ['amount' => '1000.00'], $this->staff);

    expect(fn () => pay($this->period->fresh(), ['amount' => '0.01'], $this->staff))
        ->toThrow(PaymentExceedsBalanceException::class, '$ 0,00');
});

it('RF-65: valida el importe contra el saldo vigente al confirmar, no contra el que se vio al abrir', function () {
    // La pantalla se abrió con el período en 1000 de saldo...
    $stale = ContractPeriod::query()->findOrFail($this->period->id);

    // ...y otra persona registró un pago mientras tanto.
    pay($this->period, ['amount' => '700.00'], User::factory()->create());

    expect(fn () => pay($stale, ['amount' => '500.00'], $this->staff))
        ->toThrow(PaymentExceedsBalanceException::class, '$ 300,00')
        ->and($this->period->fresh()->paid_amount)->toBe('700.00');
});

it('RF-66: rechaza una fecha posterior a la actual', function () {
    try {
        pay($this->period, ['paid_on' => '2026-10-16'], $this->staff);
        $this->fail('Se esperaba un rechazo.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('paid_on');
    }

    expect($this->period->payments()->count())->toBe(0);
});

it('RF-66: la fecha actual es la argentina aunque en UTC ya sea el día siguiente', function () {
    // 22:30 del 15 de octubre en Argentina es el 16 en UTC.
    Carbon::setTestNow(CarbonImmutable::parse('2026-10-16 01:30:00', 'UTC'));

    $payment = pay($this->period, ['paid_on' => '2026-10-15'], $this->staff);

    expect($payment->paid_on->toDateString())->toBe('2026-10-15')
        ->and(fn () => pay($this->period, ['paid_on' => '2026-10-16'], $this->staff))->toThrow(ValidationException::class);
});

it('RF-66: rechaza una fecha vacía o inválida', function (mixed $date) {
    expect(fn () => pay($this->period, ['paid_on' => $date], $this->staff))->toThrow(ValidationException::class);
})->with(['vacía' => '', 'nula' => null, 'inexistente' => '2026-02-31', 'texto' => 'ayer']);

it('RF-67: admite cualquier fecha pasada, aunque sea anterior al mes del período o al inicio del contrato', function () {
    $contract = Contract::factory()->startingOn('2026-06-01')->create();
    $period = ContractPeriod::factory()->create(['contract_id' => $contract->id, 'month' => '2026-08-01', 'fee' => '1000.00', 'total' => '1000.00']);

    $payment = pay($period, ['paid_on' => '2020-01-01'], $this->staff);

    expect($payment->paid_on->toDateString())->toBe('2020-01-01');
});

it('RF-62: no admite el pago 101 y muestra el motivo', function () {
    $period = ContractPeriod::factory()->create(['fee' => '1000.00', 'total' => '1000.00']);
    Payment::factory()->count(100)->create(['contract_period_id' => $period->id, 'amount' => '1.00']);

    expect(fn () => pay($period, ['amount' => '1.00'], $this->staff))
        ->toThrow(PeriodLimitExceededException::class, '100')
        ->and($period->payments()->count())->toBe(100);
});

it('RF-62: un pago anulado no cuenta para el tope de pagos vigentes', function () {
    $period = ContractPeriod::factory()->create(['fee' => '1000.00', 'total' => '1000.00']);
    Payment::factory()->count(99)->create(['contract_period_id' => $period->id, 'amount' => '1.00']);
    Payment::factory()->voided()->count(5)->create(['contract_period_id' => $period->id, 'amount' => '1.00']);

    pay($period, ['amount' => '1.00'], $this->staff);

    expect($period->payments()->valid()->count())->toBe(100);
});

it('RF-24, RF-35: admite pagos con el contrato cancelado, finalizado o con el cliente archivado', function (string $case) {
    $contract = match ($case) {
        'cancelado' => Contract::factory()->cancelled()->create(),
        'finalizado' => Contract::factory()->finished()->create(),
        'cliente archivado' => Contract::factory()->create(),
    };
    $period = ContractPeriod::factory()->create(['contract_id' => $contract->id, 'fee' => '1000.00', 'total' => '1000.00']);

    if ($case === 'cliente archivado') {
        Client::query()->findOrFail($contract->client_id)->delete();
    }

    $payment = pay($period->fresh(), ['amount' => '400.00'], $this->staff);

    expect($payment->exists)->toBeTrue()
        ->and($period->fresh()->paid_amount)->toBe('400.00');
})->with(['cancelado', 'finalizado', 'cliente archivado']);

it('RF-79: deja un asiento con el pago, el autor y la fecha', function () {
    $payment = pay($this->period, ['amount' => '250.50', 'paid_on' => '2026-10-10', 'method' => 'transfer', 'reference' => 'Transf. 1'], $this->staff);

    $entry = PeriodHistory::query()
        ->where('contract_period_id', $this->period->id)
        ->where('field', PeriodHistoryField::PaymentRegistered)
        ->sole();

    expect($entry->old_value)->toBeNull()
        ->and($entry->new_value)->toBe([
            'id' => $payment->id,
            'amount' => '250.50',
            'paid_on' => '2026-10-10',
            'method' => 'transfer',
            'reference' => 'Transf. 1',
        ])
        ->and($entry->author_id)->toBe($this->staff->id)
        ->and($entry->created_at)->not->toBeNull();
});

it('RF-79: un pago rechazado no deja asiento ni pago', function () {
    try {
        pay($this->period, ['amount' => '5000.00'], $this->staff);
    } catch (PaymentExceedsBalanceException) {
    }

    expect(PeriodHistory::query()->where('contract_period_id', $this->period->id)->count())->toBe(0)
        ->and($this->period->payments()->count())->toBe(0);
});

it('RNF-2: el importe se redondea al centavo más cercano', function () {
    $payment = pay($this->period, ['amount' => '10.126'], $this->staff);

    expect($payment->amount)->toBe('10.13')
        ->and($this->period->fresh()->paid_amount)->toBe('10.13');
});
