<?php

use App\Actions\AddBudgetItemAction;
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
    $this->budget = Budget::factory()->create();
    $this->service = Service::factory()->create([
        'name' => 'Diseño de logotipo',
        'description' => 'Isotipo y variantes',
        'list_price' => '100.00',
    ]);
});

function addItem(Budget $budget, Service $service, mixed $quantity, ?string $description, User $actor): BudgetItem
{
    return app(AddBudgetItemAction::class)->handle($budget, $service, $quantity, $description, $actor);
}

function itemErrors(Closure $callback): array
{
    try {
        $callback();
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    return [];
}

it('RF-21, RF-22, RF-25: agrega a un borrador o enviado un ítem con nombre, descripción y precio de lista copiados', function (string $state) {
    $budget = Budget::factory()->{$state}()->create();

    $item = addItem($budget, $this->service, 3, null, $this->actor)->refresh();

    expect($item->budget_id)->toBe($budget->id)
        ->and($item->service_id)->toBe($this->service->id)
        ->and($item->name)->toBe('Diseño de logotipo')
        ->and($item->description)->toBe('Isotipo y variantes')
        ->and($item->unit_price)->toBe('100.00')
        ->and($item->quantity)->toBe(3)
        ->and($item->amount)->toBe('300.00');
})->with(['draft', 'sent']);

it('RF-22: el ítem puede llevar una descripción propia en lugar de la del servicio', function () {
    $item = addItem($this->budget, $this->service, 1, 'Solo isotipo', $this->actor);

    expect($item->refresh()->description)->toBe('Solo isotipo');
});

it('RF-21: rechaza agregar a un presupuesto aceptado o rechazado', function (string $state) {
    $closed = Budget::factory()->{$state}()->create();

    expect(fn () => addItem($closed, $this->service, 1, null, $this->actor))
        ->toThrow(BudgetNotEditableException::class);

    expect($closed->items()->count())->toBe(0)
        ->and(BudgetHistory::query()->count())->toBe(0);
})->with(['accepted', 'rejected']);

it('RF-8, RF-21: rechaza agregar un servicio desactivado', function () {
    $inactive = Service::factory()->inactive()->create();

    $errors = itemErrors(fn () => addItem($this->budget, $inactive, 1, null, $this->actor));

    expect($errors)->toHaveKey('service_id')
        ->and($this->budget->items()->count())->toBe(0);
});

it('RF-23: el mismo servicio se puede agregar más de una vez como ítems separados', function () {
    addItem($this->budget, $this->service, 1, null, $this->actor);
    addItem($this->budget, $this->service, 2, 'Variante B', $this->actor);

    expect($this->budget->items()->count())->toBe(2)
        ->and($this->budget->items()->pluck('service_id')->unique()->all())->toBe([$this->service->id]);
});

it('RF-24: la cantidad es un entero mayor que cero', function (mixed $quantity, bool $valid) {
    $errors = itemErrors(fn () => addItem($this->budget, $this->service, $quantity, null, $this->actor));

    expect(array_key_exists('quantity', $errors))->toBe(! $valid)
        ->and($this->budget->items()->count())->toBe($valid ? 1 : 0);
})->with([
    'uno' => [1, true],
    'texto numérico' => ['5', true],
    'grande' => [1000, true],
    'cero' => [0, false],
    'negativa' => [-3, false],
    'decimal' => [1.5, false],
    'texto' => ['abc', false],
    'vacía' => ['', false],
    'nula' => [null, false],
]);

it('RF-29, RF-6: el precio copiado es siempre el de lista vigente al agregar, sin tocar los ítems anteriores', function () {
    $before = addItem($this->budget, $this->service, 1, null, $this->actor);

    $this->service->update(['list_price' => '250.00']);

    $after = addItem($this->budget, $this->service, 1, null, $this->actor);

    expect($before->refresh()->unit_price)->toBe('100.00')
        ->and($after->refresh()->unit_price)->toBe('250.00');
});

it('RF-31, RNF-4: admite hasta 100 ítems y rechaza el siguiente indicando el motivo', function () {
    BudgetItem::factory()->for($this->budget)->count(99)->create(['unit_price' => '1.00', 'quantity' => 1]);

    addItem($this->budget, $this->service, 1, null, $this->actor);

    expect($this->budget->items()->count())->toBe(100);

    $errors = itemErrors(fn () => addItem($this->budget, $this->service, 1, null, $this->actor));

    expect($errors)->toHaveKey('items')
        ->and($errors['items'][0])->toContain('100')
        ->and($this->budget->items()->count())->toBe(100);
});

it('RF-35, RF-38: recalcula el subtotal y el total con cada ítem agregado', function () {
    $other = Service::factory()->create(['list_price' => '50.50']);

    addItem($this->budget, $this->service, 2, null, $this->actor);
    addItem($this->budget, $other, 1, null, $this->actor);

    $this->budget->refresh();

    expect($this->budget->subtotal)->toBe('250.50')
        ->and($this->budget->discount_amount)->toBe('0.00')
        ->and($this->budget->total)->toBe('250.50');
});

it('RF-33: cada ítem agregado registra valor anterior, nuevo, autor y fecha', function () {
    $item = addItem($this->budget, $this->service, 2, 'Con variantes', $this->actor);

    $history = BudgetHistory::query()->where('budget_id', $this->budget->id)->sole();

    expect($history->field)->toBe(BudgetHistoryField::ItemAdded)
        ->and($history->author_id)->toBe($this->actor->id)
        ->and($history->created_at)->not->toBeNull()
        ->and($history->old_value)->toBeNull()
        ->and($history->new_value)->toBe([
            'id' => $item->id,
            'service_id' => $this->service->id,
            'name' => 'Diseño de logotipo',
            'description' => 'Con variantes',
            'unit_price' => '100.00',
            'quantity' => 2,
            'amount' => '200.00',
        ]);
});
