<?php

use App\Actions\CreateServiceAction;
use App\Models\Service;
use App\Models\ServicePriceHistory;
use App\Models\User;
use App\Models\WorkCategory;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    app()->setLocale('es');
    $this->actor = User::factory()->staff()->create();
    $this->category = WorkCategory::factory()->create(['name' => 'Branding']);
});

function serviceData(array $overrides = []): array
{
    return array_merge([
        'name' => 'Diseño de logotipo',
        'description' => 'Isotipo y variantes de color',
        'work_category_id' => WorkCategory::query()->value('id'),
        'list_price' => '15000.00',
    ], $overrides);
}

function serviceErrors(Closure $callback): array
{
    try {
        $callback();
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    return [];
}

it('RF-1, RF-2, RF-5: da de alta un servicio activo con su categoría y asienta el primer precio', function () {
    $service = app(CreateServiceAction::class)->handle(serviceData(), $this->actor)->refresh();

    expect($service->name)->toBe('Diseño de logotipo')
        ->and($service->description)->toBe('Isotipo y variantes de color')
        ->and($service->category->is($this->category))->toBeTrue()
        ->and($service->list_price)->toBe('15000.00')
        ->and($service->is_active)->toBeTrue();

    $history = ServicePriceHistory::query()->where('service_id', $service->id)->sole();

    expect($history->old_price)->toBeNull()
        ->and($history->new_price)->toBe('15000.00')
        ->and($history->author_id)->toBe($this->actor->id)
        ->and($history->created_at)->not->toBeNull();
});

it('RF-1: la descripción es opcional', function () {
    $service = app(CreateServiceAction::class)->handle(serviceData(['description' => null]), $this->actor);

    expect($service->refresh()->description)->toBeNull();
});

it('RF-2: la categoría es obligatoria y debe existir', function (mixed $category) {
    $errors = serviceErrors(fn () => app(CreateServiceAction::class)->handle(
        serviceData(['work_category_id' => $category]),
        $this->actor,
    ));

    expect($errors)->toHaveKey('work_category_id')
        ->and(Service::query()->count())->toBe(0);
})->with([
    'vacía' => '',
    'inexistente' => 9999,
]);

it('RF-3: rechaza un nombre repetido, sin distinguir mayúsculas ni espacios exteriores, y nombra el servicio existente', function (string $name, bool $existingIsActive) {
    $existing = Service::factory()->create(['name' => 'Diseño de logotipo', 'is_active' => $existingIsActive]);
    $before = ServicePriceHistory::query()->count();

    $errors = serviceErrors(fn () => app(CreateServiceAction::class)->handle(
        serviceData(['name' => $name]),
        $this->actor,
    ));

    expect($errors)->toHaveKey('name')
        ->and($errors['name'][0])->toContain('Diseño de logotipo')
        ->and(Service::query()->count())->toBe(1)
        ->and(ServicePriceHistory::query()->count())->toBe($before)
        ->and($existing->refresh()->name)->toBe('Diseño de logotipo');
})->with([
    'igual, activo' => ['Diseño de logotipo', true],
    'en mayúsculas, activo' => ['DISEÑO DE LOGOTIPO', true],
    'con espacios, activo' => ['  Diseño de logotipo  ', true],
    'igual, desactivado' => ['Diseño de logotipo', false],
    'con espacios y mayúsculas, desactivado' => [' diseño de LOGOTIPO ', false],
]);

it('RF-1: exige nombre, de hasta 150 caracteres', function () {
    expect(serviceErrors(fn () => app(CreateServiceAction::class)->handle(serviceData(['name' => '']), $this->actor)))->toHaveKey('name')
        ->and(serviceErrors(fn () => app(CreateServiceAction::class)->handle(serviceData(['name' => str_repeat('a', 151)]), $this->actor)))->toHaveKey('name')
        ->and(serviceErrors(fn () => app(CreateServiceAction::class)->handle(serviceData(['name' => str_repeat('a', 150)]), $this->actor)))->toBe([]);
});

it('RF-1, RNF-1: el precio de lista es obligatorio, no negativo y con a lo sumo 2 decimales', function (mixed $price, bool $valid) {
    $errors = serviceErrors(fn () => app(CreateServiceAction::class)->handle(
        serviceData(['list_price' => $price]),
        $this->actor,
    ));

    expect(array_key_exists('list_price', $errors))->toBe(! $valid);
})->with([
    'entero' => ['100', true],
    'un decimal' => ['100.5', true],
    'dos decimales' => ['100.55', true],
    'cero' => ['0', true],
    'tres decimales' => ['100.555', false],
    'negativo' => ['-1', false],
    'texto' => ['abc', false],
    'vacío' => ['', false],
    'demasiado grande' => ['100000000', false],
]);

it('RNF-1: el precio se guarda con exactamente 2 decimales', function () {
    $service = app(CreateServiceAction::class)->handle(serviceData(['list_price' => '12.5']), $this->actor);

    expect($service->refresh()->list_price)->toBe('12.50')
        ->and($service->priceHistories()->sole()->new_price)->toBe('12.50');
});

it('RF-9, RF-10: el historial de precios del servicio se consulta en orden', function () {
    $service = app(CreateServiceAction::class)->handle(serviceData(), $this->actor);

    expect($service->priceHistories)->toHaveCount(1)
        ->and($service->priceHistories()->toSql())->toContain('order by');
});
