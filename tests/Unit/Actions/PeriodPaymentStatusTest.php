<?php

use App\Actions\RecalculatePeriodTotalsAction;
use App\Enums\PaymentStatus;
use App\Models\ContractPeriod;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

beforeEach(function () {
    app()->setLocale('es');
    // 12:00 del 15 de octubre de 2026 en Argentina.
    Carbon::setTestNow(CarbonImmutable::parse('2026-10-15 15:00:00', 'UTC'));
});

afterEach(fn () => Carbon::setTestNow());

function periodOf(string $total, string $paid = '0.00', array $attributes = []): ContractPeriod
{
    return ContractPeriod::factory()->create([
        'fee' => $total,
        'total' => $total,
        'paid_amount' => $paid,
        'due_date' => '2026-10-25',
        ...$attributes,
    ]);
}

it('RF-69: el saldo es el total menos lo pagado', function () {
    expect(periodOf('1000.00', '350.25')->balance)->toBe('649.75')
        ->and(periodOf('1000.00')->balance)->toBe('1000.00')
        ->and(periodOf('1000.00', '1000.00')->balance)->toBe('0.00');
});

it('RF-70, RF-71: con saldo y sin pagos el período está pendiente', function () {
    expect(periodOf('1000.00')->payment_status)->toBe(PaymentStatus::Pending);
});

it('RF-70, RF-72: con saldo y al menos un pago vigente el período está parcial', function () {
    expect(periodOf('1000.00', '0.01')->payment_status)->toBe(PaymentStatus::Partial)
        ->and(periodOf('1000.00', '999.99')->payment_status)->toBe(PaymentStatus::Partial);
});

it('RF-70, RF-73: con saldo cero el período está pagado', function () {
    expect(periodOf('1000.00', '1000.00')->payment_status)->toBe(PaymentStatus::Paid);
});

it('RF-73: un período con total cero y sin pagos está pagado', function () {
    $period = periodOf('0.00');

    expect($period->balance)->toBe('0.00')
        ->and($period->payment_status)->toBe(PaymentStatus::Paid);
});

it('RF-70: cada período tiene exactamente un estado de pago', function () {
    $periods = [
        periodOf('1000.00'),
        periodOf('1000.00', '400.00'),
        periodOf('1000.00', '1000.00'),
        periodOf('0.00'),
    ];

    foreach ($periods as $period) {
        $matches = collect([
            ContractPeriod::query()->pending()->whereKey($period->id)->exists(),
            ContractPeriod::query()->partial()->whereKey($period->id)->exists(),
            ContractPeriod::query()->paid()->whereKey($period->id)->exists(),
        ])->filter()->count();

        expect($matches)->toBe(1);
    }
});

it('RF-72, RF-73, RF-69: anular un pago devuelve el período a su estado anterior', function () {
    $period = periodOf('1000.00');
    $first = Payment::factory()->create(['contract_period_id' => $period->id, 'amount' => '400.00']);
    $second = Payment::factory()->create(['contract_period_id' => $period->id, 'amount' => '600.00']);

    expect(app(RecalculatePeriodTotalsAction::class)->handle($period)->payment_status)->toBe(PaymentStatus::Paid);

    $second->forceFill(['voided_at' => now()])->save();

    $partial = app(RecalculatePeriodTotalsAction::class)->handle($period);

    expect($partial->payment_status)->toBe(PaymentStatus::Partial)
        ->and($partial->balance)->toBe('600.00');

    $first->forceFill(['voided_at' => now()])->save();

    $pending = app(RecalculatePeriodTotalsAction::class)->handle($period);

    expect($pending->payment_status)->toBe(PaymentStatus::Pending)
        ->and($pending->balance)->toBe('1000.00');
});

it('RF-74: un período con saldo y vencimiento anterior a hoy está vencido', function () {
    $overdue = periodOf('1000.00', '0.00', ['due_date' => '2026-10-14']);
    $partialOverdue = periodOf('1000.00', '300.00', ['due_date' => '2026-09-10']);

    expect($overdue->isOverdue())->toBeTrue()
        ->and($partialOverdue->isOverdue())->toBeTrue();
});

it('RF-74: el día del vencimiento todavía no está vencido', function () {
    expect(periodOf('1000.00', '0.00', ['due_date' => '2026-10-15'])->isOverdue())->toBeFalse()
        ->and(periodOf('1000.00', '0.00', ['due_date' => '2026-10-16'])->isOverdue())->toBeFalse();
});

it('RF-74: un período sin saldo no está vencido aunque su fecha haya pasado', function () {
    expect(periodOf('1000.00', '1000.00', ['due_date' => '2026-09-10'])->isOverdue())->toBeFalse()
        ->and(periodOf('0.00', '0.00', ['due_date' => '2026-09-10'])->isOverdue())->toBeFalse();
});

it('RF-74: vencido no cambia el estado de pago', function () {
    $pending = periodOf('1000.00', '0.00', ['due_date' => '2026-09-10']);
    $partial = periodOf('1000.00', '300.00', ['due_date' => '2026-09-10']);

    expect($pending->isOverdue())->toBeTrue()
        ->and($pending->payment_status)->toBe(PaymentStatus::Pending)
        ->and($partial->isOverdue())->toBeTrue()
        ->and($partial->payment_status)->toBe(PaymentStatus::Partial);
});

it('RF-74: el vencido se cuenta con la fecha de negocio argentina, no con la de UTC', function () {
    // 22:30 del 15 de octubre en Argentina es ya el 16 en UTC.
    Carbon::setTestNow(CarbonImmutable::parse('2026-10-16 01:30:00', 'UTC'));

    expect(periodOf('1000.00', '0.00', ['due_date' => '2026-10-15'])->isOverdue())->toBeFalse()
        ->and(periodOf('1000.00', '0.00', ['due_date' => '2026-10-14'])->isOverdue())->toBeTrue();
});

it('RF-70, RF-74: los scopes filtran por estado de pago y por vencidos', function () {
    $pending = periodOf('1000.00');
    $partial = periodOf('1000.00', '400.00');
    $paid = periodOf('1000.00', '1000.00');
    $free = periodOf('0.00');
    $overduePending = periodOf('1000.00', '0.00', ['due_date' => '2026-09-10']);
    $overduePartial = periodOf('1000.00', '400.00', ['due_date' => '2026-09-10']);
    $overduePaid = periodOf('1000.00', '1000.00', ['due_date' => '2026-09-10']);

    $ids = fn ($query): array => $query->orderBy('id')->pluck('id')->all();

    expect($ids(ContractPeriod::query()->pending()))->toBe([$pending->id, $overduePending->id])
        ->and($ids(ContractPeriod::query()->partial()))->toBe([$partial->id, $overduePartial->id])
        ->and($ids(ContractPeriod::query()->paid()))->toBe([$paid->id, $free->id, $overduePaid->id])
        ->and($ids(ContractPeriod::query()->overdue()))->toBe([$overduePending->id, $overduePartial->id]);
});
