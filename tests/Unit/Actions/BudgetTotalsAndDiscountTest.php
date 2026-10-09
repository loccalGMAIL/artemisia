<?php

use App\Actions\RecalculateBudgetTotalsAction;
use App\Actions\RemoveBudgetItemAction;
use App\Actions\SetBudgetDiscountAction;
use App\Enums\BudgetDiscountType;
use App\Enums\BudgetHistoryField;
use App\Exceptions\BudgetNotEditableException;
use App\Models\Budget;
use App\Models\BudgetHistory;
use App\Models\BudgetItem;
use App\Models\User;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    app()->setLocale('es');
    $this->actor = User::factory()->staff()->create();
    $this->budget = Budget::factory()->create();
});

function itemsWorth(Budget $budget, string ...$prices): void
{
    foreach ($prices as $price) {
        BudgetItem::factory()->for($budget)->create(['unit_price' => $price, 'quantity' => 1]);
    }

    app(RecalculateBudgetTotalsAction::class)->handle($budget);
}

function setDiscount(Budget $budget, ?string $type, mixed $value, User $actor): Budget
{
    return app(SetBudgetDiscountAction::class)->handle($budget, $type, $value, $actor);
}

function discountErrors(Closure $callback): array
{
    try {
        $callback();
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    return [];
}

it('RF-35, RF-38: el subtotal es la suma de los importes de los ítems y el total es el subtotal sin descuento', function () {
    BudgetItem::factory()->for($this->budget)->create(['unit_price' => '100.00', 'quantity' => 3]);
    BudgetItem::factory()->for($this->budget)->create(['unit_price' => '49.99', 'quantity' => 2]);

    app(RecalculateBudgetTotalsAction::class)->handle($this->budget);

    $this->budget->refresh();

    expect($this->budget->subtotal)->toBe('399.98')
        ->and($this->budget->discount_amount)->toBe('0.00')
        ->and($this->budget->total)->toBe('399.98');
});

it('RF-35: un presupuesto sin ítems tiene todos sus importes en cero', function () {
    app(RecalculateBudgetTotalsAction::class)->handle($this->budget);

    expect($this->budget->refresh()->subtotal)->toBe('0.00')
        ->and($this->budget->total)->toBe('0.00');
});

it('RF-36, RF-38: carga un descuento porcentual sobre el subtotal', function () {
    itemsWorth($this->budget, '1000.00');

    setDiscount($this->budget, 'percentage', '10', $this->actor);

    $this->budget->refresh();

    expect($this->budget->discount_type)->toBe(BudgetDiscountType::Percentage)
        ->and($this->budget->discount_value)->toBe('10.00')
        ->and($this->budget->subtotal)->toBe('1000.00')
        ->and($this->budget->discount_amount)->toBe('100.00')
        ->and($this->budget->total)->toBe('900.00');
});

it('RF-36, RF-38: carga un descuento de monto fijo', function () {
    itemsWorth($this->budget, '1000.00');

    setDiscount($this->budget, 'fixed', '250.50', $this->actor);

    $this->budget->refresh();

    expect($this->budget->discount_type)->toBe(BudgetDiscountType::Fixed)
        ->and($this->budget->discount_amount)->toBe('250.50')
        ->and($this->budget->total)->toBe('749.50');
});

it('RF-37: a lo sumo un descuento por presupuesto: cargar otro reemplaza al anterior', function () {
    itemsWorth($this->budget, '1000.00');

    setDiscount($this->budget, 'percentage', '10', $this->actor);
    setDiscount($this->budget, 'fixed', '50', $this->actor);

    $this->budget->refresh();

    expect($this->budget->discount_type)->toBe(BudgetDiscountType::Fixed)
        ->and($this->budget->discount_value)->toBe('50.00')
        ->and($this->budget->discount_amount)->toBe('50.00')
        ->and($this->budget->total)->toBe('950.00');
});

it('RF-36: quita el descuento', function () {
    itemsWorth($this->budget, '1000.00');
    setDiscount($this->budget, 'percentage', '10', $this->actor);

    setDiscount($this->budget, null, null, $this->actor);

    $this->budget->refresh();

    expect($this->budget->discount_type)->toBeNull()
        ->and($this->budget->discount_value)->toBeNull()
        ->and($this->budget->discount_amount)->toBe('0.00')
        ->and($this->budget->total)->toBe('1000.00');
});

it('RNF-3: el descuento porcentual admite de 0 a 100 inclusive', function (string $percentage, bool $valid) {
    itemsWorth($this->budget, '200.00');

    $errors = discountErrors(fn () => setDiscount($this->budget, 'percentage', $percentage, $this->actor));

    expect(array_key_exists('discount_value', $errors))->toBe(! $valid);
})->with([
    'cero' => ['0', true],
    'medio' => ['50.5', true],
    'cien' => ['100', true],
    'más de cien' => ['100.01', false],
    'negativo' => ['-1', false],
]);

it('RNF-3: con 100 por ciento el total queda en cero', function () {
    itemsWorth($this->budget, '200.00');

    setDiscount($this->budget, 'percentage', '100', $this->actor);

    expect($this->budget->refresh()->total)->toBe('0.00');
});

it('RF-39: rechaza un monto fijo que dejaría el total por debajo de cero y muestra el motivo', function () {
    itemsWorth($this->budget, '300.00');

    $errors = discountErrors(fn () => setDiscount($this->budget, 'fixed', '300.01', $this->actor));

    expect($errors)->toHaveKey('discount_value')
        ->and($errors['discount_value'][0])->toContain('total')
        ->and($this->budget->refresh()->discount_type)->toBeNull()
        ->and($this->budget->total)->toBe('300.00')
        ->and(BudgetHistory::query()->count())->toBe(0);

    setDiscount($this->budget, 'fixed', '300.00', $this->actor);

    expect($this->budget->refresh()->total)->toBe('0.00');
});

it('RF-39: sin ítems ningún monto fijo es válido', function () {
    $errors = discountErrors(fn () => setDiscount($this->budget, 'fixed', '1', $this->actor));

    expect($errors)->toHaveKey('discount_value');
});

it('RNF-2: redondea el descuento porcentual al múltiplo de 0,01 más cercano, la mitad hacia arriba', function (string $subtotal, string $percentage, string $discount, string $total) {
    itemsWorth($this->budget, $subtotal);

    setDiscount($this->budget, 'percentage', $percentage, $this->actor);

    $this->budget->refresh();

    expect($this->budget->discount_amount)->toBe($discount)
        ->and($this->budget->total)->toBe($total);
})->with([
    'sin redondeo' => ['100.00', '12.50', '12.50', '87.50'],
    'baja' => ['100.05', '12.50', '12.51', '87.54'],
    'la mitad exacta sube' => ['0.05', '10', '0.01', '0.04'],
    'casi cinco' => ['33.33', '15', '5.00', '28.33'],
    'bajo la mitad' => ['0.04', '10', '0.00', '0.04'],
]);

it('RNF-1: todo importe tiene exactamente 2 decimales', function () {
    itemsWorth($this->budget, '10', '0.1');
    setDiscount($this->budget, 'fixed', '1.5', $this->actor);

    $this->budget->refresh();

    foreach (['subtotal', 'discount_amount', 'total', 'discount_value'] as $field) {
        expect($this->budget->{$field})->toMatch('/^\d+\.\d{2}$/');
    }
});

it('RF-36: el valor del descuento se valida', function (?string $type, mixed $value) {
    itemsWorth($this->budget, '500.00');

    $errors = discountErrors(fn () => setDiscount($this->budget, $type, $value, $this->actor));

    expect($errors)->not->toBe([]);
})->with([
    'tipo desconocido' => ['weekly', '10'],
    'tipo sin valor' => ['fixed', null],
    'tipo con valor vacío' => ['percentage', ''],
    'valor no numérico' => ['fixed', 'abc'],
    'valor negativo' => ['fixed', '-5'],
    'tres decimales' => ['fixed', '1.234'],
]);

it('RF-40: registra la carga, la modificación y la baja del descuento con valor anterior, nuevo, autor y fecha', function () {
    itemsWorth($this->budget, '1000.00');

    setDiscount($this->budget, 'percentage', '10', $this->actor);
    setDiscount($this->budget, 'fixed', '75', $this->actor);
    setDiscount($this->budget, null, null, $this->actor);

    $entries = $this->budget->histories()->get();

    expect($entries->pluck('field')->unique()->all())->toBe([BudgetHistoryField::DiscountChanged])
        ->and($entries->pluck('author_id')->unique()->all())->toBe([$this->actor->id])
        ->and($entries[0]->created_at)->not->toBeNull()
        ->and($entries[0]->old_value)->toBe(['discount_type' => null, 'discount_value' => null, 'discount_amount' => '0.00'])
        ->and($entries[0]->new_value)->toBe(['discount_type' => 'percentage', 'discount_value' => '10.00', 'discount_amount' => '100.00'])
        ->and($entries[1]->old_value)->toBe(['discount_type' => 'percentage', 'discount_value' => '10.00', 'discount_amount' => '100.00'])
        ->and($entries[1]->new_value)->toBe(['discount_type' => 'fixed', 'discount_value' => '75.00', 'discount_amount' => '75.00'])
        ->and($entries[2]->new_value)->toBe(['discount_type' => null, 'discount_value' => null, 'discount_amount' => '0.00']);
});

it('RF-40: repetir el mismo descuento no registra nada', function () {
    itemsWorth($this->budget, '1000.00');
    setDiscount($this->budget, 'percentage', '10', $this->actor);

    setDiscount($this->budget, 'percentage', '10.00', $this->actor);

    expect(BudgetHistory::query()->count())->toBe(1);
});

it('RF-48: no se carga ni se modifica el descuento de un presupuesto aceptado o rechazado', function (string $state) {
    $closed = Budget::factory()->{$state}()->create();

    expect(fn () => setDiscount($closed, 'fixed', '10', $this->actor))->toThrow(BudgetNotEditableException::class);

    expect($closed->refresh()->discount_type)->toBeNull()
        ->and(BudgetHistory::query()->count())->toBe(0);
})->with(['accepted', 'rejected']);

it('RF-38: el descuento porcentual se recalcula al cambiar los ítems', function () {
    itemsWorth($this->budget, '1000.00');
    setDiscount($this->budget, 'percentage', '10', $this->actor);

    BudgetItem::factory()->for($this->budget)->create(['unit_price' => '500.00', 'quantity' => 1]);
    app(RecalculateBudgetTotalsAction::class)->handle($this->budget);

    expect($this->budget->refresh()->subtotal)->toBe('1500.00')
        ->and($this->budget->discount_amount)->toBe('150.00')
        ->and($this->budget->total)->toBe('1350.00');
});

it('RF-39: si al quitar ítems un monto fijo supera al subtotal, el total no baja de cero', function () {
    itemsWorth($this->budget, '200.00', '100.00');
    setDiscount($this->budget, 'fixed', '250', $this->actor);

    $big = $this->budget->items()->orderBy('id')->first();

    app(RemoveBudgetItemAction::class)->handle($big, $this->actor);

    $this->budget->refresh();

    expect($this->budget->subtotal)->toBe('100.00')
        ->and($this->budget->discount_amount)->toBe('100.00')
        ->and($this->budget->total)->toBe('0.00');
});

it('RF-41: un presupuesto de abono mensual presenta su total como importe mensual', function () {
    $monthly = Budget::factory()->monthly()->create();
    $single = Budget::factory()->create();

    expect($monthly->totalLabel())->toBe('Importe mensual')
        ->and($single->totalLabel())->toBe('Total');
});
