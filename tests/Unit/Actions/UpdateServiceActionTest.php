<?php

use App\Actions\ToggleServiceActiveAction;
use App\Actions\UpdateServiceAction;
use App\Models\BudgetItem;
use App\Models\Service;
use App\Models\ServicePriceHistory;
use App\Models\User;
use App\Models\WorkCategory;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    app()->setLocale('es');
    $this->actor = User::factory()->staff()->create();
    $this->service = Service::factory()->create([
        'name' => 'Diseño de logotipo',
        'description' => 'Isotipo',
        'list_price' => '100.00',
    ]);
});

function updateData(Service $service, array $overrides = []): array
{
    return array_merge([
        'name' => $service->name,
        'description' => $service->description,
        'work_category_id' => $service->work_category_id,
        'list_price' => $service->list_price,
    ], $overrides);
}

function updateErrors(Closure $callback): array
{
    try {
        $callback();
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    return [];
}

it('RF-4: modifica nombre, descripción, categoría y precio de lista', function () {
    $category = WorkCategory::factory()->create(['name' => 'Redes']);

    app(UpdateServiceAction::class)->handle($this->service, [
        'name' => 'Logo y manual de marca',
        'description' => 'Con manual',
        'work_category_id' => $category->id,
        'list_price' => '250.5',
    ], $this->actor);

    $this->service->refresh();

    expect($this->service->name)->toBe('Logo y manual de marca')
        ->and($this->service->description)->toBe('Con manual')
        ->and($this->service->work_category_id)->toBe($category->id)
        ->and($this->service->list_price)->toBe('250.50');
});

it('RF-5: un cambio de precio de lista registra precio anterior, nuevo, autor y fecha', function () {
    app(UpdateServiceAction::class)->handle($this->service, updateData($this->service, ['list_price' => '150.00']), $this->actor);

    $history = ServicePriceHistory::query()->where('service_id', $this->service->id)->sole();

    expect($history->old_price)->toBe('100.00')
        ->and($history->new_price)->toBe('150.00')
        ->and($history->author_id)->toBe($this->actor->id)
        ->and($history->created_at)->not->toBeNull();

    app(UpdateServiceAction::class)->handle($this->service->refresh(), updateData($this->service, ['list_price' => '175.00']), $this->actor);

    expect($this->service->priceHistories()->pluck('new_price')->all())->toBe(['150.00', '175.00']);
});

it('RF-5: editar otros datos sin tocar el precio no registra ningún asiento', function () {
    app(UpdateServiceAction::class)->handle($this->service, updateData($this->service, ['description' => 'Otra descripción']), $this->actor);

    app(UpdateServiceAction::class)->handle($this->service->refresh(), updateData($this->service, ['list_price' => '100']), $this->actor);

    expect(ServicePriceHistory::query()->count())->toBe(0);
});

it('RF-6: un cambio de precio de lista no altera el precio copiado de ningún ítem existente', function () {
    $items = BudgetItem::factory()->count(3)->create([
        'service_id' => $this->service->id,
        'unit_price' => '100.00',
        'quantity' => 2,
    ]);

    app(UpdateServiceAction::class)->handle($this->service, updateData($this->service, ['list_price' => '999.99']), $this->actor);

    expect($this->service->refresh()->list_price)->toBe('999.99');

    foreach ($items as $item) {
        expect($item->refresh()->unit_price)->toBe('100.00')
            ->and($item->amount)->toBe('200.00');
    }
});

it('RF-3: renombrar a un nombre ya usado se rechaza, incluso por uno desactivado, y conservar el propio nombre se admite', function (bool $otherIsActive) {
    Service::factory()->create(['name' => 'Papelería comercial', 'is_active' => $otherIsActive]);

    $errors = updateErrors(fn () => app(UpdateServiceAction::class)->handle(
        $this->service,
        updateData($this->service, ['name' => ' PAPELERÍA comercial ']),
        $this->actor,
    ));

    expect($errors)->toHaveKey('name')
        ->and($errors['name'][0])->toContain('Papelería comercial')
        ->and($this->service->refresh()->name)->toBe('Diseño de logotipo');

    expect(updateErrors(fn () => app(UpdateServiceAction::class)->handle(
        $this->service,
        updateData($this->service, ['name' => 'DISEÑO DE LOGOTIPO']),
        $this->actor,
    )))->toBe([]);
})->with([
    'otro activo' => true,
    'otro desactivado' => false,
]);

it('RF-4, RNF-1: valida los mismos datos que el alta', function () {
    expect(updateErrors(fn () => app(UpdateServiceAction::class)->handle($this->service, updateData($this->service, ['name' => '']), $this->actor)))->toHaveKey('name')
        ->and(updateErrors(fn () => app(UpdateServiceAction::class)->handle($this->service, updateData($this->service, ['list_price' => '-5']), $this->actor)))->toHaveKey('list_price')
        ->and(updateErrors(fn () => app(UpdateServiceAction::class)->handle($this->service, updateData($this->service, ['list_price' => '1.234']), $this->actor)))->toHaveKey('list_price')
        ->and(updateErrors(fn () => app(UpdateServiceAction::class)->handle($this->service, updateData($this->service, ['work_category_id' => 9999]), $this->actor)))->toHaveKey('work_category_id')
        ->and($this->service->refresh()->list_price)->toBe('100.00')
        ->and(ServicePriceHistory::query()->count())->toBe(0);
});

it('RF-7, RF-9: desactiva y reactiva un servicio sin eliminarlo ni perder su historial de precios', function () {
    app(UpdateServiceAction::class)->handle($this->service, updateData($this->service, ['list_price' => '120']), $this->actor);

    $deactivated = app(ToggleServiceActiveAction::class)->handle($this->service->refresh());

    expect($deactivated->is_active)->toBeFalse()
        ->and($this->service->refresh()->is_active)->toBeFalse()
        ->and(Service::query()->whereKey($this->service->id)->exists())->toBeTrue()
        ->and($this->service->priceHistories)->toHaveCount(1);

    $reactivated = app(ToggleServiceActiveAction::class)->handle($this->service);

    expect($reactivated->is_active)->toBeTrue()
        ->and($this->service->refresh()->priceHistories)->toHaveCount(1);
});

it('RF-8: un servicio desactivado no se ofrece para agregar a un presupuesto', function () {
    $active = Service::factory()->create();
    $inactive = Service::factory()->inactive()->create();

    expect(Service::active()->pluck('id')->all())->toContain($active->id, $this->service->id)
        ->and(Service::active()->pluck('id')->all())->not->toContain($inactive->id);

    app(ToggleServiceActiveAction::class)->handle($this->service);

    expect(Service::active()->pluck('id')->all())->not->toContain($this->service->id);
});
