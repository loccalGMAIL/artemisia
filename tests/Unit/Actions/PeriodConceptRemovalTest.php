<?php

use App\Actions\RecalculatePeriodTotalsAction;
use App\Actions\RemovePeriodConceptAction;
use App\Enums\ConceptType;
use App\Enums\PeriodHistoryField;
use App\Exceptions\PeriodTotalException;
use App\Models\ContractPeriod;
use App\Models\Payment;
use App\Models\PeriodConcept;
use App\Models\PeriodHistory;
use App\Models\User;

beforeEach(function () {
    app()->setLocale('es');
    $this->staff = User::factory()->create();
});

/**
 * A period of 1000.00 with a 300.00 extra and a 100.00 discount, so a total of 1200.00.
 *
 * @return array{0: ContractPeriod, 1: PeriodConcept, 2: PeriodConcept}
 */
function periodWithConcepts(): array
{
    $period = ContractPeriod::factory()->create(['fee' => '1000.00', 'total' => '1000.00']);
    $extra = PeriodConcept::factory()->create(['contract_period_id' => $period->id, 'amount' => '300.00', 'description' => 'Banner extra']);
    $discount = PeriodConcept::factory()->discount()->create(['contract_period_id' => $period->id, 'amount' => '100.00']);
    app(RecalculatePeriodTotalsAction::class)->handle($period);

    return [$period->fresh(), $extra, $discount];
}

it('RF-47, RF-49: quita un extra y el total se recalcula', function () {
    [$period, $extra] = periodWithConcepts();

    app(RemovePeriodConceptAction::class)->handle($extra, $this->staff);

    expect($period->fresh()->total)->toBe('900.00')
        ->and($period->concepts()->count())->toBe(1);
});

it('RF-47, RF-49: quita un descuento y el total se recalcula', function () {
    [$period, , $discount] = periodWithConcepts();

    app(RemovePeriodConceptAction::class)->handle($discount, $this->staff);

    expect($period->fresh()->total)->toBe('1300.00')
        ->and($period->concepts()->count())->toBe(1);
});

it('RF-51: rechaza quitar un extra si el total quedaría por debajo de lo ya pagado', function () {
    [$period, $extra] = periodWithConcepts();
    Payment::factory()->create(['contract_period_id' => $period->id, 'amount' => '1000.00']);
    app(RecalculatePeriodTotalsAction::class)->handle($period);

    expect(fn () => app(RemovePeriodConceptAction::class)->handle($extra, $this->staff))
        ->toThrow(PeriodTotalException::class, 'pagado')
        ->and(PeriodConcept::query()->whereKey($extra->id)->exists())->toBeTrue()
        ->and($period->fresh()->total)->toBe('1200.00');
});

it('RF-51: quitar un extra se admite si el total sigue cubriendo lo pagado', function () {
    [$period, $extra] = periodWithConcepts();
    Payment::factory()->create(['contract_period_id' => $period->id, 'amount' => '900.00']);
    app(RecalculatePeriodTotalsAction::class)->handle($period);

    app(RemovePeriodConceptAction::class)->handle($extra, $this->staff);

    expect($period->fresh()->total)->toBe('900.00')
        ->and($period->fresh()->paid_amount)->toBe('900.00');
});

it('RF-48: la fila del concepto se borra y su foto queda en el historial del período', function () {
    [$period, $extra] = periodWithConcepts();

    app(RemovePeriodConceptAction::class)->handle($extra, $this->staff);

    $entry = PeriodHistory::query()
        ->where('contract_period_id', $period->id)
        ->where('field', PeriodHistoryField::ConceptRemoved)
        ->sole();

    expect(PeriodConcept::query()->whereKey($extra->id)->exists())->toBeFalse()
        ->and($entry->old_value)->toBe([
            'id' => $extra->id,
            'type' => ConceptType::Extra->value,
            'description' => 'Banner extra',
            'amount' => '300.00',
        ])
        ->and($entry->new_value)->toBe(['removed' => true]);
});

it('RF-52: el asiento de la baja lleva el autor y la fecha', function () {
    [$period, , $discount] = periodWithConcepts();

    app(RemovePeriodConceptAction::class)->handle($discount, $this->staff);

    $entry = PeriodHistory::query()->where('field', PeriodHistoryField::ConceptRemoved)->sole();

    expect($entry->author_id)->toBe($this->staff->id)
        ->and($entry->created_at)->not->toBeNull()
        ->and($entry->old_value['type'])->toBe('discount')
        ->and($entry->contract_period_id)->toBe($period->id);
});

it('RF-52: un rechazo no deja asiento ni cambia el total', function () {
    [$period, $extra] = periodWithConcepts();
    Payment::factory()->create(['contract_period_id' => $period->id, 'amount' => '1200.00']);
    app(RecalculatePeriodTotalsAction::class)->handle($period);

    try {
        app(RemovePeriodConceptAction::class)->handle($extra, $this->staff);
    } catch (PeriodTotalException) {
    }

    expect(PeriodHistory::query()->where('field', PeriodHistoryField::ConceptRemoved)->count())->toBe(0)
        ->and($period->fresh()->total)->toBe('1200.00');
});

it('RF-49: quitar todos los conceptos deja el total en la cuota', function () {
    [$period, $extra, $discount] = periodWithConcepts();

    app(RemovePeriodConceptAction::class)->handle($extra, $this->staff);
    app(RemovePeriodConceptAction::class)->handle($discount, $this->staff);

    expect($period->fresh()->total)->toBe('1000.00')
        ->and($period->concepts()->count())->toBe(0);
});
