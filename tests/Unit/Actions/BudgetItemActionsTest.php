<?php

use App\Actions\RefreshBudgetItemPriceAction;
use App\Actions\RemoveBudgetItemAction;
use App\Actions\UpdateBudgetItemDescriptionAction;
use App\Actions\UpdateBudgetItemQuantityAction;
use App\Enums\BudgetHistoryField;
use App\Exceptions\BudgetNotEditableException;
use App\Models\Budget;
use App\Models\BudgetHistory;
use App\Models\BudgetItem;
use App\Models\Service;
use App\Models\User;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    app()->setLocale('es');
    $this->actor = User::factory()->staff()->create();
    $this->service = Service::factory()->create(['list_price' => '100.00']);
    $this->budget = Budget::factory()->create();
    $this->item = BudgetItem::factory()->for($this->budget)->create([
        'service_id' => $this->service->id,
        'name' => 'Logo',
        'description' => 'Isotipo',
        'unit_price' => '100.00',
        'quantity' => 2,
    ]);
    $this->budget->update(['subtotal' => '200.00', 'total' => '200.00']);
});

function lastHistory(Budget $budget): BudgetHistory
{
    return BudgetHistory::query()->where('budget_id', $budget->id)->latest('id')->firstOrFail();
}

it('RF-26, RF-33: modifica la cantidad, recalcula los totales y registra valor anterior, nuevo, autor y fecha', function () {
    app(UpdateBudgetItemQuantityAction::class)->handle($this->item, 5, $this->actor);

    expect($this->item->refresh()->quantity)->toBe(5)
        ->and($this->item->amount)->toBe('500.00')
        ->and($this->budget->refresh()->subtotal)->toBe('500.00')
        ->and($this->budget->total)->toBe('500.00');

    $history = lastHistory($this->budget);

    expect($history->field)->toBe(BudgetHistoryField::ItemUpdated)
        ->and($history->author_id)->toBe($this->actor->id)
        ->and($history->created_at)->not->toBeNull()
        ->and($history->old_value)->toMatchArray(['id' => $this->item->id, 'quantity' => 2, 'amount' => '200.00'])
        ->and($history->new_value)->toMatchArray(['id' => $this->item->id, 'quantity' => 5, 'amount' => '500.00']);
});

it('RF-24: la cantidad modificada sigue siendo un entero mayor que cero', function (mixed $quantity) {
    expect(fn () => app(UpdateBudgetItemQuantityAction::class)->handle($this->item, $quantity, $this->actor))
        ->toThrow(ValidationException::class);

    expect($this->item->refresh()->quantity)->toBe(2)
        ->and(BudgetHistory::query()->count())->toBe(0);
})->with([0, -1, 1.5, 'abc', '', null]);

it('RF-27, RF-33: modifica la descripción y registra el cambio, sin tocar importes', function () {
    app(UpdateBudgetItemDescriptionAction::class)->handle($this->item, 'Isotipo y manual de uso', $this->actor);

    expect($this->item->refresh()->description)->toBe('Isotipo y manual de uso')
        ->and($this->item->amount)->toBe('200.00')
        ->and($this->budget->refresh()->total)->toBe('200.00');

    $history = lastHistory($this->budget);

    expect($history->field)->toBe(BudgetHistoryField::ItemUpdated)
        ->and($history->old_value)->toMatchArray(['description' => 'Isotipo'])
        ->and($history->new_value)->toMatchArray(['description' => 'Isotipo y manual de uso']);
});

it('RF-27: la descripción puede quedar vacía', function () {
    app(UpdateBudgetItemDescriptionAction::class)->handle($this->item, '  ', $this->actor);

    expect($this->item->refresh()->description)->toBeNull();
});

it('RF-28, RF-29: actualiza el precio copiado al precio de lista vigente del servicio, y a ningún otro', function () {
    $other = BudgetItem::factory()->for($this->budget)->create([
        'service_id' => $this->service->id,
        'unit_price' => '100.00',
        'quantity' => 1,
    ]);

    $this->service->update(['list_price' => '130.00']);

    app(RefreshBudgetItemPriceAction::class)->handle($this->item, $this->actor);

    expect($this->item->refresh()->unit_price)->toBe('130.00')
        ->and($this->item->amount)->toBe('260.00')
        ->and($other->refresh()->unit_price)->toBe('100.00')
        ->and($this->budget->refresh()->subtotal)->toBe('360.00');

    $history = lastHistory($this->budget);

    expect($history->field)->toBe(BudgetHistoryField::ItemUpdated)
        ->and($history->old_value)->toMatchArray(['unit_price' => '100.00', 'amount' => '200.00'])
        ->and($history->new_value)->toMatchArray(['unit_price' => '130.00', 'amount' => '260.00']);
});

it('RF-28: si el precio de lista no cambió, actualizar no registra nada', function () {
    app(RefreshBudgetItemPriceAction::class)->handle($this->item, $this->actor);

    expect(BudgetHistory::query()->count())->toBe(0)
        ->and($this->item->refresh()->unit_price)->toBe('100.00');
});

it('RF-26, RF-27: repetir el mismo valor no registra nada', function () {
    app(UpdateBudgetItemQuantityAction::class)->handle($this->item, 2, $this->actor);
    app(UpdateBudgetItemDescriptionAction::class)->handle($this->item, 'Isotipo', $this->actor);

    expect(BudgetHistory::query()->count())->toBe(0);
});

it('RF-30, RF-32, RF-33: quita un ítem, recalcula los totales y conserva su snapshot en el historial', function () {
    $keep = BudgetItem::factory()->for($this->budget)->create(['unit_price' => '50.00', 'quantity' => 1]);

    app(RemoveBudgetItemAction::class)->handle($this->item, $this->actor);

    expect(BudgetItem::query()->whereKey($this->item->id)->exists())->toBeFalse()
        ->and($this->budget->items()->pluck('id')->all())->toBe([$keep->id])
        ->and($this->budget->refresh()->subtotal)->toBe('50.00')
        ->and($this->budget->total)->toBe('50.00');

    $history = lastHistory($this->budget);

    expect($history->field)->toBe(BudgetHistoryField::ItemRemoved)
        ->and($history->author_id)->toBe($this->actor->id)
        ->and($history->old_value)->toMatchArray([
            'id' => $this->item->id,
            'name' => 'Logo',
            'description' => 'Isotipo',
            'unit_price' => '100.00',
            'quantity' => 2,
            'amount' => '200.00',
        ])
        ->and($history->new_value)->toBe(['removed' => true]);
});

it('RF-30: se puede quitar el último ítem y el presupuesto queda en cero', function () {
    app(RemoveBudgetItemAction::class)->handle($this->item, $this->actor);

    expect($this->budget->items()->count())->toBe(0)
        ->and($this->budget->refresh()->total)->toBe('0.00');
});

it('RF-48: ninguna acción de ítems funciona sobre un presupuesto aceptado o rechazado', function (string $state) {
    $this->budget->update(['status' => $state]);

    $calls = [
        fn () => app(UpdateBudgetItemQuantityAction::class)->handle($this->item, 9, $this->actor),
        fn () => app(UpdateBudgetItemDescriptionAction::class)->handle($this->item, 'Otra', $this->actor),
        fn () => app(RefreshBudgetItemPriceAction::class)->handle($this->item, $this->actor),
        fn () => app(RemoveBudgetItemAction::class)->handle($this->item, $this->actor),
    ];

    foreach ($calls as $call) {
        expect($call)->toThrow(BudgetNotEditableException::class);
    }

    expect($this->item->refresh()->quantity)->toBe(2)
        ->and($this->item->description)->toBe('Isotipo')
        ->and(BudgetItem::query()->whereKey($this->item->id)->exists())->toBeTrue()
        ->and(BudgetHistory::query()->count())->toBe(0);
})->with(['accepted', 'rejected']);

it('RF-29: no existe forma de fijar un precio distinto del de lista en un ítem', function () {
    foreach ([UpdateBudgetItemQuantityAction::class, UpdateBudgetItemDescriptionAction::class, RefreshBudgetItemPriceAction::class] as $action) {
        $parameters = array_map(fn ($parameter) => $parameter->getName(), (new ReflectionMethod($action, 'handle'))->getParameters());

        expect($parameters)->not->toContain('price')->not->toContain('unitPrice')->not->toContain('unit_price');
    }
});
