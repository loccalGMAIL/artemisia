<?php

use App\Actions\AddPeriodDiscountAction;
use App\Actions\AddPeriodExtraAction;
use App\Actions\RecalculatePeriodTotalsAction;
use App\Enums\ConceptType;
use App\Enums\PeriodHistoryField;
use App\Exceptions\PeriodLimitExceededException;
use App\Exceptions\PeriodTotalException;
use App\Models\ContractPeriod;
use App\Models\Payment;
use App\Models\PeriodConcept;
use App\Models\PeriodHistory;
use App\Models\User;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    app()->setLocale('es');
    $this->staff = User::factory()->create();
});

function periodWithFee(string $fee = '1000.00'): ContractPeriod
{
    return ContractPeriod::factory()->create(['fee' => $fee, 'total' => $fee]);
}

function addExtra(ContractPeriod $period, array $data, User $actor): PeriodConcept
{
    return app(AddPeriodExtraAction::class)->handle($period, $data, $actor);
}

function addDiscount(ContractPeriod $period, array $data, User $actor): PeriodConcept
{
    return app(AddPeriodDiscountAction::class)->handle($period, $data, $actor);
}

it('RF-42, RF-49: agrega un extra con descripción e importe y lo suma al total', function () {
    $period = periodWithFee('1000.00');

    $concept = addExtra($period, ['description' => 'Diseño de banner adicional', 'amount' => '250.50'], $this->staff);

    expect($concept->type)->toBe(ConceptType::Extra)
        ->and($concept->description)->toBe('Diseño de banner adicional')
        ->and($concept->amount)->toBe('250.50')
        ->and($concept->created_by)->toBe($this->staff->id)
        ->and($period->fresh()->total)->toBe('1250.50');
});

it('RF-43, RF-49: agrega un descuento con justificación y lo resta del total', function () {
    $period = periodWithFee('1000.00');

    $concept = addDiscount($period, ['description' => 'Bonificación por pago adelantado', 'amount' => '100.00'], $this->staff);

    expect($concept->type)->toBe(ConceptType::Discount)
        ->and($concept->description)->toBe('Bonificación por pago adelantado')
        ->and($period->fresh()->total)->toBe('900.00');
});

it('RF-44: rechaza un descuento sin justificación e indica que falta', function (mixed $justification) {
    $period = periodWithFee();

    expect(fn () => addDiscount($period, ['description' => $justification, 'amount' => '100.00'], $this->staff))
        ->toThrow(ValidationException::class);

    try {
        addDiscount($period, ['description' => $justification, 'amount' => '100.00'], $this->staff);
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('description')
            ->and($e->errors()['description'][0])->toContain('justificación');
    }

    expect($period->concepts()->count())->toBe(0)
        ->and($period->fresh()->total)->toBe('1000.00');
})->with(['vacía' => '', 'solo espacios' => '   ', 'nula' => null]);

it('RF-42: rechaza un extra sin descripción', function () {
    $period = periodWithFee();

    expect(fn () => addExtra($period, ['description' => '  ', 'amount' => '100.00'], $this->staff))
        ->toThrow(ValidationException::class)
        ->and($period->concepts()->count())->toBe(0);
});

it('RF-42, RF-43: el importe tiene que ser mayor que cero', function (string $kind, mixed $amount) {
    $period = periodWithFee();
    $add = $kind === 'extra' ? 'addExtra' : 'addDiscount';

    expect(fn () => $add($period, ['description' => 'Motivo', 'amount' => $amount], $this->staff))
        ->toThrow(ValidationException::class)
        ->and($period->concepts()->count())->toBe(0);
})->with([
    'extra en cero' => ['extra', '0'],
    'extra negativo' => ['extra', '-5'],
    'extra no numérico' => ['extra', 'abc'],
    'extra vacío' => ['extra', ''],
    'descuento en cero' => ['discount', '0.00'],
    'descuento negativo' => ['discount', '-5'],
]);

it('RF-45: admite más de un extra y más de un descuento en el mismo período', function () {
    $period = periodWithFee('1000.00');

    addExtra($period, ['description' => 'Extra 1', 'amount' => '100.00'], $this->staff);
    addExtra($period, ['description' => 'Extra 2', 'amount' => '200.00'], $this->staff);
    addDiscount($period, ['description' => 'Descuento 1', 'amount' => '50.00'], $this->staff);
    addDiscount($period, ['description' => 'Descuento 2', 'amount' => '25.00'], $this->staff);

    expect($period->concepts()->count())->toBe(4)
        ->and($period->fresh()->total)->toBe('1225.00');
});

it('RF-46: no admite el concepto 101 y muestra el motivo', function (string $kind) {
    $period = periodWithFee('1000.00');
    PeriodConcept::factory()->count(100)->create(['contract_period_id' => $period->id, 'amount' => '1.00']);
    $add = $kind === 'extra' ? 'addExtra' : 'addDiscount';

    expect(fn () => $add($period, ['description' => 'Uno más', 'amount' => '1.00'], $this->staff))
        ->toThrow(PeriodLimitExceededException::class, '100')
        ->and($period->concepts()->count())->toBe(100);
})->with(['extra', 'discount']);

it('RF-46: un concepto quitado no cuenta para el tope', function () {
    $period = periodWithFee('1000.00');
    PeriodConcept::factory()->count(99)->create(['contract_period_id' => $period->id, 'amount' => '1.00']);

    addExtra($period, ['description' => 'El número 100', 'amount' => '1.00'], $this->staff);

    expect($period->concepts()->count())->toBe(100);
});

it('RF-49, RNF-2: el total es la cuota más extras menos descuentos, redondeado al centavo', function () {
    $period = periodWithFee('1000.10');

    $extra = addExtra($period, ['description' => 'Extra con milésimas', 'amount' => '10.126'], $this->staff);
    addDiscount($period, ['description' => 'Descuento', 'amount' => '0.25'], $this->staff);

    expect($extra->amount)->toBe('10.13')
        ->and($period->fresh()->total)->toBe('1009.98');
});

it('RF-50: rechaza un descuento que dejaría el total por debajo de cero', function () {
    $period = periodWithFee('1000.00');

    expect(fn () => addDiscount($period, ['description' => 'Demasiado', 'amount' => '1000.01'], $this->staff))
        ->toThrow(PeriodTotalException::class)
        ->and($period->concepts()->count())->toBe(0)
        ->and($period->fresh()->total)->toBe('1000.00');
});

it('RF-50: un descuento que deja el total justo en cero se admite', function () {
    $period = periodWithFee('1000.00');

    addDiscount($period, ['description' => 'Bonificación total', 'amount' => '1000.00'], $this->staff);

    expect($period->fresh()->total)->toBe('0.00');
});

it('RF-51: rechaza un descuento que dejaría el total por debajo de lo ya pagado', function () {
    $period = periodWithFee('1000.00');
    Payment::factory()->create(['contract_period_id' => $period->id, 'amount' => '600.00']);
    app(RecalculatePeriodTotalsAction::class)->handle($period);

    expect(fn () => addDiscount($period->fresh(), ['description' => 'Descuento', 'amount' => '500.00'], $this->staff))
        ->toThrow(PeriodTotalException::class)
        ->and($period->fresh()->total)->toBe('1000.00');

    addDiscount($period->fresh(), ['description' => 'Descuento', 'amount' => '400.00'], $this->staff);

    expect($period->fresh()->total)->toBe('600.00');
});

it('RF-49, RF-69: el recálculo toma lo pagado de los pagos vigentes y no de los anulados', function () {
    $period = periodWithFee('1000.00');
    Payment::factory()->create(['contract_period_id' => $period->id, 'amount' => '300.00']);
    Payment::factory()->voided()->create(['contract_period_id' => $period->id, 'amount' => '200.00']);

    $recalculated = app(RecalculatePeriodTotalsAction::class)->handle($period);

    expect($recalculated->paid_amount)->toBe('300.00')
        ->and($recalculated->total)->toBe('1000.00');
});

it('RF-52: deja un asiento por cada concepto agregado, con tipo, descripción, importe, autor y fecha', function () {
    $period = periodWithFee();

    $extra = addExtra($period, ['description' => 'Extra del mes', 'amount' => '75.00'], $this->staff);
    $discount = addDiscount($period, ['description' => 'Descuento del mes', 'amount' => '25.00'], $this->staff);

    $entries = PeriodHistory::query()
        ->where('contract_period_id', $period->id)
        ->where('field', PeriodHistoryField::ConceptAdded)
        ->orderBy('id')
        ->get();

    expect($entries)->toHaveCount(2)
        ->and($entries[0]->old_value)->toBeNull()
        ->and($entries[0]->new_value)->toMatchArray([
            'id' => $extra->id,
            'type' => 'extra',
            'description' => 'Extra del mes',
            'amount' => '75.00',
        ])
        ->and($entries[1]->new_value)->toMatchArray([
            'id' => $discount->id,
            'type' => 'discount',
            'description' => 'Descuento del mes',
            'amount' => '25.00',
        ])
        ->and($entries[0]->author_id)->toBe($this->staff->id)
        ->and($entries[0]->created_at)->not->toBeNull();
});

it('RF-52: un concepto rechazado no deja asiento', function () {
    $period = periodWithFee('100.00');

    try {
        addDiscount($period, ['description' => 'Demasiado', 'amount' => '500.00'], $this->staff);
    } catch (PeriodTotalException) {
    }

    expect(PeriodHistory::query()->where('contract_period_id', $period->id)->count())->toBe(0);
});
