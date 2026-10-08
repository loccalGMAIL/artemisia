<?php

use App\Filament\Staff\Resources\Services\Pages\CreateService;
use App\Filament\Staff\Resources\Services\Pages\EditService;
use App\Filament\Staff\Resources\Services\Pages\ListServices;
use App\Filament\Staff\Resources\Services\RelationManagers\PriceHistoriesRelationManager;
use App\Filament\Staff\Resources\Services\ServiceResource;
use App\Models\Service;
use App\Models\ServicePriceHistory;
use App\Models\User;
use App\Models\WorkCategory;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    app()->setLocale('es');
    Filament::setCurrentPanel('staff');
    $this->category = WorkCategory::factory()->create(['name' => 'Branding']);
});

function signInAsStaffMember(string $role = 'staff'): User
{
    $user = User::factory()->{$role}()->create();

    test()->actingAs($user);

    return $user;
}

function priceHistoryManagerFor(Service $service): Testable
{
    return Livewire::test(PriceHistoriesRelationManager::class, [
        'ownerRecord' => $service,
        'pageClass' => EditService::class,
    ]);
}

it('RF-1: admin y staff ven la sección del catálogo en la navegación', function (string $role) {
    signInAsStaffMember($role);

    $this->get('/staff')->assertOk()->assertSee('/staff/services');
    $this->get('/staff/services')->assertOk();
})->with(['staff', 'admin']);

it('RF-1, RF-2, RF-5: da de alta un servicio desde el panel y asienta el primer precio', function (string $role) {
    $actor = signInAsStaffMember($role);

    Livewire::test(CreateService::class)
        ->fillForm([
            'name' => 'Diseño de logotipo',
            'description' => 'Isotipo y variantes',
            'work_category_id' => $this->category->id,
            'list_price' => '15000',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $service = Service::query()->sole();

    expect($service->list_price)->toBe('15000.00')
        ->and($service->is_active)->toBeTrue()
        ->and($service->priceHistories()->sole()->author_id)->toBe($actor->id);
})->with(['staff', 'admin']);

it('RF-3: el formulario de alta muestra el conflicto con el servicio existente, incluso desactivado', function () {
    signInAsStaffMember();
    Service::factory()->inactive()->create(['name' => 'Diseño de logotipo']);

    Livewire::test(CreateService::class)
        ->fillForm(['name' => ' DISEÑO de logotipo ', 'work_category_id' => $this->category->id, 'list_price' => '100'])
        ->call('create')
        ->assertHasFormErrors(['name'])
        ->assertSee('Ya existe un servicio con el nombre «Diseño de logotipo».');

    expect(Service::query()->count())->toBe(1);
});

it('RF-1, RF-2: el alta exige nombre, categoría y precio', function () {
    signInAsStaffMember();

    Livewire::test(CreateService::class)
        ->fillForm(['name' => '', 'work_category_id' => null, 'list_price' => ''])
        ->call('create')
        ->assertHasFormErrors(['name', 'work_category_id', 'list_price']);
});

it('RF-4, RF-5: modifica un servicio y su cambio de precio queda en el historial', function () {
    $actor = signInAsStaffMember();
    $service = Service::factory()->create(['name' => 'Logo', 'list_price' => '100.00', 'work_category_id' => $this->category->id]);

    Livewire::test(EditService::class, ['record' => $service->getKey()])
        ->fillForm(['name' => 'Logo premium', 'list_price' => '180.50'])
        ->call('save')
        ->assertHasNoFormErrors();

    $history = $service->refresh()->priceHistories()->sole();

    expect($service->name)->toBe('Logo premium')
        ->and($service->list_price)->toBe('180.50')
        ->and($history->old_price)->toBe('100.00')
        ->and($history->new_price)->toBe('180.50')
        ->and($history->author_id)->toBe($actor->id);
});

it('RF-7: desactiva y reactiva un servicio desde el listado', function () {
    signInAsStaffMember();
    $service = Service::factory()->create();

    Livewire::test(ListServices::class)
        ->callAction(TestAction::make('toggleActive')->table($service));

    expect($service->refresh()->is_active)->toBeFalse();

    Livewire::test(ListServices::class)
        ->callAction(TestAction::make('toggleActive')->table($service));

    expect($service->refresh()->is_active)->toBeTrue();
});

it('RF-8, RF-9: el listado conserva los servicios desactivados y distingue su estado', function () {
    signInAsStaffMember();
    $active = Service::factory()->create();
    $inactive = Service::factory()->inactive()->create();

    Livewire::test(ListServices::class)
        ->assertCanSeeTableRecords([$active, $inactive])
        ->assertCanRenderTableColumn('is_active');
});

it('RF-9: la sección no ofrece eliminar servicios', function () {
    signInAsStaffMember();
    $service = Service::factory()->create();

    Livewire::test(ListServices::class)
        ->assertActionDoesNotExist(TestAction::make('delete')->table($service));

    Livewire::test(EditService::class, ['record' => $service->getKey()])
        ->assertActionDoesNotExist('delete');

    expect(Gate::forUser(User::factory()->admin()->create())->denies('delete', $service))->toBeTrue();
});

it('RF-10: consulta el historial de precios del servicio en orden cronológico y de solo lectura', function () {
    signInAsStaffMember();
    $service = Service::factory()->create();
    $later = ServicePriceHistory::factory()->for($service)->create(['created_at' => now()->subDay()]);
    $earlier = ServicePriceHistory::factory()->for($service)->create(['created_at' => now()->subDays(4)]);

    priceHistoryManagerFor($service)
        ->assertCanSeeTableRecords([$earlier, $later], inOrder: true)
        ->assertActionDoesNotExist(TestAction::make('create')->table())
        ->assertActionDoesNotExist(TestAction::make('edit')->table($later))
        ->assertActionDoesNotExist(TestAction::make('delete')->table($later));
});

it('RF-60: un usuario client recibe permiso insuficiente sobre el catálogo', function () {
    $client = User::factory()->client()->create();
    $service = Service::factory()->create();

    $this->actingAs($client);

    $this->get('/staff/services')->assertForbidden();

    foreach (['viewAny', 'create'] as $ability) {
        $inspection = Gate::forUser($client)->inspect($ability, Service::class);

        expect($inspection->denied())->toBeTrue()->and($inspection->message())->toContain('permiso');
    }

    foreach (['view', 'update', 'toggleActive'] as $ability) {
        $inspection = Gate::forUser($client)->inspect($ability, $service);

        expect($inspection->denied())->toBeTrue()->and($inspection->message())->toContain('permiso');
    }
});

it('RF-1, RF-4, RF-7, RF-10: admin y staff tienen los mismos permisos sobre el catálogo', function (string $role) {
    $user = User::factory()->{$role}()->create();
    $service = Service::factory()->create();

    foreach (['viewAny', 'create'] as $ability) {
        expect(Gate::forUser($user)->allows($ability, Service::class))->toBeTrue();
    }

    foreach (['view', 'update', 'toggleActive'] as $ability) {
        expect(Gate::forUser($user)->allows($ability, $service))->toBeTrue();
    }

    expect(ServiceResource::getPages())->toHaveKeys(['index', 'create', 'edit']);
})->with(['staff', 'admin']);
