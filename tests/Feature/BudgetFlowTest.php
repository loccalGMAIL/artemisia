<?php

use App\Actions\RecalculateBudgetTotalsAction;
use App\Enums\BudgetHistoryField;
use App\Enums\BudgetStatus;
use App\Filament\Staff\Resources\Budgets\Pages\CreateBudget;
use App\Filament\Staff\Resources\Budgets\Pages\EditBudget;
use App\Filament\Staff\Resources\Budgets\Pages\ViewBudget;
use App\Filament\Staff\Resources\Budgets\RelationManagers\ItemsRelationManager;
use App\Models\Budget;
use App\Models\BudgetItem;
use App\Models\Client;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    app()->setLocale('es');
    Filament::setCurrentPanel('staff');
    $this->travelTo(now()->setDate(2026, 10, 7)->setTime(10, 0));
    $this->actor = User::factory()->staff()->create();
    $this->actingAs($this->actor);
});

function itemsManager(Budget $budget, string $page = ViewBudget::class): Testable
{
    return Livewire::test(ItemsRelationManager::class, ['ownerRecord' => $budget, 'pageClass' => $page]);
}

it('RF-11, RF-12, RF-14: crea un presupuesto en borrador desde el formulario', function () {
    $client = Client::factory()->create(['first_name' => 'Ana', 'last_name' => 'Pérez']);

    Livewire::test(CreateBudget::class)
        ->fillForm([
            'client_id' => $client->id,
            'title' => 'Identidad visual',
            'modality' => 'monthly',
            'issue_date' => '2026-10-07',
            'validity_date' => '2026-11-07',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $budget = Budget::query()->sole();

    expect($budget->status)->toBe(BudgetStatus::Draft)
        ->and($budget->client_id)->toBe($client->id)
        ->and($budget->created_by)->toBe($this->actor->id)
        ->and($budget->modality->value)->toBe('monthly');
});

it('RF-17, RF-19: el formulario de alta muestra los motivos de rechazo', function () {
    $client = Client::factory()->create();

    Livewire::test(CreateBudget::class)
        ->fillForm(['client_id' => null, 'title' => 'X', 'modality' => 'single', 'issue_date' => '2026-10-07', 'validity_date' => '2026-10-07'])
        ->call('create')
        ->assertHasFormErrors(['client_id', 'validity_date']);

    expect(Budget::query()->count())->toBe(0);

    Livewire::test(CreateBudget::class)
        ->fillForm(['client_id' => $client->id, 'title' => '', 'modality' => null, 'validity_date' => null])
        ->call('create')
        ->assertHasFormErrors(['title', 'modality', 'validity_date']);
});

it('RF-26 de clientes: el formulario ofrece solo clientes activos y no archivados como destinatarios', function () {
    $active = Client::factory()->create(['first_name' => 'Ana', 'last_name' => 'Activa']);
    Client::factory()->inactive()->create(['first_name' => 'Ana', 'last_name' => 'Inactiva']);
    Client::factory()->archived()->create(['first_name' => 'Ana', 'last_name' => 'Archivada']);

    Livewire::test(CreateBudget::class)
        ->assertFormFieldExists('client_id', checkFieldUsing: fn (Select $field): bool => array_keys($field->getSearchResults('ana')) === [$active->id]);
});

it('RF-16, RF-33: modifica la cabecera desde el formulario de edición y queda en el historial', function () {
    $budget = Budget::factory()->create(['title' => 'Original']);

    Livewire::test(EditBudget::class, ['record' => $budget->getKey()])
        ->fillForm(['title' => 'Editado', 'validity_date' => '2026-12-31'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($budget->refresh()->title)->toBe('Editado')
        ->and($budget->histories()->sole()->field)->toBe(BudgetHistoryField::HeaderChanged);
});

it('RF-20: un presupuesto cuyo cliente fue archivado sigue editable y muestra a ese cliente', function () {
    $client = Client::factory()->create(['first_name' => 'Rosa', 'last_name' => 'Sosa']);
    $budget = Budget::factory()->for($client)->create(['title' => 'Antes']);
    $client->delete();

    Livewire::test(EditBudget::class, ['record' => $budget->getKey()])
        ->assertSee('Rosa Sosa')
        ->fillForm(['title' => 'Después'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($budget->refresh()->title)->toBe('Después');
});

it('RF-8, RF-21, RF-22: agrega ítems con servicios activos, que son los únicos que se ofrecen', function () {
    $budget = Budget::factory()->create();
    $active = Service::factory()->create(['name' => 'Logo', 'list_price' => '100.00']);
    $inactive = Service::factory()->inactive()->create(['name' => 'Servicio viejo']);

    itemsManager($budget)
        ->mountAction(TestAction::make('addItem')->table())
        ->assertFormFieldExists('service_id', checkFieldUsing: fn (Select $field): bool => array_keys($field->getOptions()) === [$active->id]);

    itemsManager($budget)
        ->callAction(TestAction::make('addItem')->table(), ['service_id' => $active->id, 'quantity' => 3, 'description' => null])
        ->assertHasNoFormErrors();

    $item = $budget->items()->sole();

    expect($item->name)->toBe('Logo')
        ->and($item->unit_price)->toBe('100.00')
        ->and($item->quantity)->toBe(3)
        ->and($budget->refresh()->total)->toBe('300.00')
        ->and($inactive->is_active)->toBeFalse();
});

it('RF-24, RF-31: el formulario de ítems muestra el rechazo por cantidad y por tope', function () {
    $budget = Budget::factory()->create();
    $service = Service::factory()->create();

    itemsManager($budget)
        ->callAction(TestAction::make('addItem')->table(), ['service_id' => $service->id, 'quantity' => 0])
        ->assertHasFormErrors(['quantity']);

    BudgetItem::factory()->for($budget)->count(100)->create(['unit_price' => '1.00', 'quantity' => 1]);

    itemsManager($budget)
        ->callAction(TestAction::make('addItem')->table(), ['service_id' => $service->id, 'quantity' => 1])
        ->assertNotified('Un presupuesto admite hasta 100 ítems.');

    expect($budget->items()->count())->toBe(100);
});

it('RF-26, RF-27, RF-28, RF-30: modifica cantidad y descripción, actualiza el precio y quita un ítem', function () {
    $service = Service::factory()->create(['list_price' => '100.00']);
    $budget = Budget::factory()->create();
    $item = BudgetItem::factory()->for($budget)->create([
        'service_id' => $service->id, 'unit_price' => '100.00', 'quantity' => 1, 'description' => 'Original',
    ]);

    itemsManager($budget)
        ->callAction(TestAction::make('editItem')->table($item), ['quantity' => 4, 'description' => 'Con cambios'])
        ->assertHasNoFormErrors();

    expect($item->refresh()->quantity)->toBe(4)
        ->and($item->description)->toBe('Con cambios');

    $service->update(['list_price' => '150.00']);

    itemsManager($budget)->callAction(TestAction::make('refreshPrice')->table($item));

    expect($item->refresh()->unit_price)->toBe('150.00')
        ->and($budget->refresh()->subtotal)->toBe('600.00');

    itemsManager($budget)->callAction(TestAction::make('removeItem')->table($item));

    expect(BudgetItem::query()->whereKey($item->id)->exists())->toBeFalse()
        ->and($budget->refresh()->total)->toBe('0.00');
});

it('RF-36, RF-39: carga un descuento desde la ficha y muestra el rechazo si deja el total negativo', function () {
    $budget = Budget::factory()->create();
    BudgetItem::factory()->for($budget)->create(['unit_price' => '1000.00', 'quantity' => 1]);
    app(RecalculateBudgetTotalsAction::class)->handle($budget);

    Livewire::test(ViewBudget::class, ['record' => $budget->getKey()])
        ->callAction('setDiscount', ['discount_type' => 'percentage', 'discount_value' => '10'])
        ->assertHasNoFormErrors();

    expect($budget->refresh()->total)->toBe('900.00');

    Livewire::test(ViewBudget::class, ['record' => $budget->getKey()])
        ->callAction('setDiscount', ['discount_type' => 'fixed', 'discount_value' => '1000.01'])
        ->assertHasFormErrors(['discount_value']);

    expect($budget->refresh()->total)->toBe('900.00');

    Livewire::test(ViewBudget::class, ['record' => $budget->getKey()])
        ->callAction('setDiscount', ['discount_type' => null, 'discount_value' => null]);

    expect($budget->refresh()->total)->toBe('1000.00');
});

it('RF-43, RF-44: envía un borrador con ítems y no uno sin ítems', function () {
    $empty = Budget::factory()->create();

    Livewire::test(ViewBudget::class, ['record' => $empty->getKey()])
        ->callAction('send')
        ->assertNotified('No se puede enviar un presupuesto sin ítems.');

    expect($empty->refresh()->status)->toBe(BudgetStatus::Draft);

    $budget = Budget::factory()->create();
    BudgetItem::factory()->for($budget)->create();

    Livewire::test(ViewBudget::class, ['record' => $budget->getKey()])->callAction('send');

    expect($budget->refresh()->status)->toBe(BudgetStatus::Sent);
});

it('RF-45, RF-47, RF-49: acepta, rechaza con motivo y devuelve a enviado desde la ficha', function () {
    $toAccept = Budget::factory()->sent()->create();
    $toReject = Budget::factory()->sent()->create();

    Livewire::test(ViewBudget::class, ['record' => $toAccept->getKey()])->callAction('accept');
    Livewire::test(ViewBudget::class, ['record' => $toReject->getKey()])->callAction('reject', ['rejection_reason' => 'Muy caro']);

    expect($toAccept->refresh()->status)->toBe(BudgetStatus::Accepted)
        ->and($toReject->refresh()->status)->toBe(BudgetStatus::Rejected)
        ->and($toReject->rejection_reason)->toBe('Muy caro');

    Livewire::test(ViewBudget::class, ['record' => $toAccept->getKey()])->callAction('revert');
    Livewire::test(ViewBudget::class, ['record' => $toReject->getKey()])->callAction('revert');

    expect($toAccept->refresh()->status)->toBe(BudgetStatus::Sent)
        ->and($toReject->refresh()->status)->toBe(BudgetStatus::Sent);
});

it('RF-42: cada estado ofrece solo las acciones que le corresponden', function (string $state, array $visible) {
    $budget = Budget::factory()->{$state}()->create();

    $page = Livewire::test(ViewBudget::class, ['record' => $budget->getKey()]);

    foreach (['send', 'accept', 'reject', 'revert', 'discard', 'setDiscount'] as $action) {
        in_array($action, $visible, true)
            ? $page->assertActionVisible($action)
            : $page->assertActionHidden($action);
    }
})->with([
    'borrador' => ['draft', ['send', 'discard', 'setDiscount']],
    'enviado' => ['sent', ['accept', 'reject', 'setDiscount']],
    'aceptado' => ['accepted', ['revert']],
    'rechazado' => ['rejected', ['revert']],
]);

it('RF-48: un presupuesto aceptado o rechazado no ofrece edición de ítems ni de cabecera', function (string $state) {
    $budget = Budget::factory()->{$state}()->create();
    $item = BudgetItem::factory()->for($budget)->create();

    itemsManager($budget)
        ->assertActionHidden(TestAction::make('addItem')->table())
        ->assertActionHidden(TestAction::make('editItem')->table($item))
        ->assertActionHidden(TestAction::make('refreshPrice')->table($item))
        ->assertActionHidden(TestAction::make('removeItem')->table($item));

    Livewire::test(ViewBudget::class, ['record' => $budget->getKey()])->assertActionHidden('edit');
})->with(['accepted', 'rejected']);

it('RF-51: descarta un borrador desde la ficha y deja de mostrarse en el listado', function () {
    $budget = Budget::factory()->create();

    Livewire::test(ViewBudget::class, ['record' => $budget->getKey()])->callAction('discard');

    expect($budget->refresh()->trashed())->toBeTrue()
        ->and(Budget::query()->count())->toBe(0);
});

it('RF-11 a RF-51: recorre el flujo completo, de la alta al reenvío tras una reversión', function () {
    $client = Client::factory()->create();
    $service = Service::factory()->create(['list_price' => '200.00']);

    Livewire::test(CreateBudget::class)
        ->fillForm(['client_id' => $client->id, 'title' => 'Flujo completo', 'modality' => 'single', 'validity_date' => '2026-12-31'])
        ->call('create');

    $budget = Budget::query()->sole();

    itemsManager($budget)->callAction(TestAction::make('addItem')->table(), ['service_id' => $service->id, 'quantity' => 2]);

    Livewire::test(ViewBudget::class, ['record' => $budget->getKey()])
        ->callAction('setDiscount', ['discount_type' => 'fixed', 'discount_value' => '50'])
        ->callAction('send');

    expect($budget->refresh()->status)->toBe(BudgetStatus::Sent)
        ->and($budget->total)->toBe('350.00');

    Livewire::test(ViewBudget::class, ['record' => $budget->getKey()])->callAction('accept');

    expect($budget->refresh()->status)->toBe(BudgetStatus::Accepted);

    Livewire::test(ViewBudget::class, ['record' => $budget->getKey()])->callAction('revert');

    $item = $budget->items()->sole();

    itemsManager($budget)->callAction(TestAction::make('editItem')->table($item), ['quantity' => 5, 'description' => null]);

    expect($budget->refresh()->status)->toBe(BudgetStatus::Sent)
        ->and($budget->total)->toBe('950.00')
        ->and($budget->histories()->count())->toBeGreaterThanOrEqual(6);
});
