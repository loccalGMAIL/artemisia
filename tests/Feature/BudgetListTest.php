<?php

use App\Filament\Staff\Resources\Budgets\Pages\EditBudget;
use App\Filament\Staff\Resources\Budgets\Pages\ListBudgets;
use App\Filament\Staff\Resources\Budgets\Pages\ViewBudget;
use App\Filament\Staff\Resources\Budgets\RelationManagers\HistoriesRelationManager;
use App\Models\Budget;
use App\Models\BudgetHistory;
use App\Models\Client;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    app()->setLocale('es');
    Filament::setCurrentPanel('staff');
    $this->travelTo(now()->setDate(2026, 10, 7)->setTime(10, 0));
});

function signInStaff(string $role = 'staff'): User
{
    $user = User::factory()->{$role}()->create();

    test()->actingAs($user);

    return $user;
}

it('RF-57: staff y admin ven la sección de presupuestos y su listado', function (string $role) {
    signInStaff($role);

    $this->get('/staff')->assertOk()->assertSee('/staff/budgets');
    $this->get('/staff/budgets')->assertOk();
})->with(['staff', 'admin']);

it('RF-57: el listado muestra identificador, cliente, título, modalidad, total, estado y fecha de emisión', function () {
    signInStaff();
    $client = Client::factory()->create(['first_name' => 'Ana', 'last_name' => 'Pérez']);
    $budget = Budget::factory()->monthly()->sent()->for($client)->create([
        'title' => 'Identidad visual completa',
        'issue_date' => '2026-10-01',
        'validity_date' => '2026-10-31',
        'total' => '1234.50',
    ]);

    Livewire::test(ListBudgets::class)
        ->assertCanSeeTableRecords([$budget])
        ->assertCanRenderTableColumn('id')
        ->assertCanRenderTableColumn('client.display_name')
        ->assertCanRenderTableColumn('title')
        ->assertCanRenderTableColumn('modality')
        ->assertCanRenderTableColumn('total')
        ->assertCanRenderTableColumn('status')
        ->assertCanRenderTableColumn('issue_date')
        ->assertSee((string) $budget->id)
        ->assertSee('Ana Pérez')
        ->assertSee('Identidad visual completa')
        ->assertSee('Abono mensual')
        ->assertSee('1.234,50')
        ->assertSee('Enviado')
        ->assertSee('01/10/2026');
});

it('RF-57: el listado muestra primero los presupuestos más nuevos', function () {
    signInStaff();
    $old = Budget::factory()->create();
    $new = Budget::factory()->create();

    Livewire::test(ListBudgets::class)->assertCanSeeTableRecords([$new, $old], inOrder: true);
});

it('RF-58: filtra el listado por cliente', function () {
    signInStaff();
    $ana = Client::factory()->create(['first_name' => 'Ana', 'last_name' => 'Pérez']);
    $luis = Client::factory()->create(['first_name' => 'Luis', 'last_name' => 'Gómez']);
    $ofAna = Budget::factory()->for($ana)->count(2)->create();
    $ofLuis = Budget::factory()->for($luis)->create();

    Livewire::test(ListBudgets::class)
        ->filterTable('client_id', $ana->id)
        ->assertCanSeeTableRecords($ofAna)
        ->assertCanNotSeeTableRecords([$ofLuis]);
});

it('RF-59: filtra el listado por estado', function (string $state) {
    signInStaff();
    $draft = Budget::factory()->draft()->create();
    $sent = Budget::factory()->sent()->create();
    $accepted = Budget::factory()->accepted()->create();
    $rejected = Budget::factory()->rejected()->create();
    $all = ['draft' => $draft, 'sent' => $sent, 'accepted' => $accepted, 'rejected' => $rejected];

    $expected = $all[$state];
    unset($all[$state]);

    Livewire::test(ListBudgets::class)
        ->filterTable('status', $state)
        ->assertCanSeeTableRecords([$expected])
        ->assertCanNotSeeTableRecords(array_values($all));
})->with(['draft', 'sent', 'accepted', 'rejected']);

it('RF-52: un presupuesto descartado no aparece en el listado', function () {
    signInStaff();
    $visible = Budget::factory()->create();
    $discarded = Budget::factory()->discarded()->create();

    Livewire::test(ListBudgets::class)
        ->assertCanSeeTableRecords([$visible])
        ->assertCanNotSeeTableRecords([$discarded]);
});

it('RF-55: el listado señala como vencido un enviado con fecha de validez pasada, sin cambiar su estado', function () {
    signInStaff();
    $expired = Budget::factory()->sent()->create(['issue_date' => '2026-01-01', 'validity_date' => '2026-09-30']);

    Livewire::test(ListBudgets::class)
        ->assertCanSeeTableRecords([$expired])
        ->assertSee('Vencido')
        ->assertSee('Enviado');

    expect($expired->refresh()->status->value)->toBe('sent');
});

it('RF-55: no señala como vencido a un enviado vigente, a un borrador ni a uno cerrado', function (string $state, string $validity) {
    signInStaff();
    Budget::factory()->{$state}()->create(['issue_date' => '2026-01-01', 'validity_date' => $validity]);

    Livewire::test(ListBudgets::class)->assertDontSee('Vencido');
})->with([
    'enviado vigente' => ['sent', '2026-10-07'],
    'borrador con fecha pasada' => ['draft', '2026-03-01'],
    'aceptado con fecha pasada' => ['accepted', '2026-03-01'],
    'rechazado con fecha pasada' => ['rejected', '2026-03-01'],
]);

it('RF-54: la ficha muestra la fecha de validez', function () {
    signInStaff();
    $budget = Budget::factory()->create(['issue_date' => '2026-10-01', 'validity_date' => '2026-10-31']);

    Livewire::test(ViewBudget::class, ['record' => $budget->getKey()])
        ->assertSee('31/10/2026')
        ->assertSee('01/10/2026');
});

it('RF-41: la ficha de un abono mensual presenta el total como importe mensual', function () {
    signInStaff();
    $monthly = Budget::factory()->monthly()->create(['total' => '500.00']);
    $single = Budget::factory()->create(['total' => '500.00']);

    Livewire::test(ViewBudget::class, ['record' => $monthly->getKey()])->assertSee('Importe mensual');
    Livewire::test(ViewBudget::class, ['record' => $single->getKey()])->assertDontSee('Importe mensual');
});

it('RF-34: la ficha muestra el historial en orden cronológico y de solo lectura', function () {
    signInStaff();
    $budget = Budget::factory()->create();
    $later = BudgetHistory::factory()->for($budget)->create(['created_at' => now()->subDay()]);
    $earlier = BudgetHistory::factory()->for($budget)->create(['created_at' => now()->subDays(4)]);

    Livewire::test(HistoriesRelationManager::class, ['ownerRecord' => $budget, 'pageClass' => ViewBudget::class])
        ->assertCanSeeTableRecords([$earlier, $later], inOrder: true)
        ->assertActionDoesNotExist(TestAction::make('create')->table())
        ->assertActionDoesNotExist(TestAction::make('edit')->table($later))
        ->assertActionDoesNotExist(TestAction::make('delete')->table($later));
});

it('RF-27, RF-52: la ficha de un presupuesto descartado sigue consultable', function () {
    signInStaff();
    $discarded = Budget::factory()->discarded()->create(['title' => 'Propuesta vieja']);

    Livewire::test(ViewBudget::class, ['record' => $discarded->getKey()])->assertSee('Propuesta vieja');
});

it('RF-60: un usuario client recibe permiso insuficiente sobre cualquier acción de presupuestos', function () {
    $client = User::factory()->client()->create();
    $budget = Budget::factory()->create();

    $this->actingAs($client);

    $this->get('/staff/budgets')->assertForbidden();
    $this->get("/staff/budgets/{$budget->id}")->assertForbidden();

    foreach (['viewAny', 'create'] as $ability) {
        $inspection = Gate::forUser($client)->inspect($ability, Budget::class);

        expect($inspection->denied())->toBeTrue()->and($inspection->message())->toContain('permiso');
    }

    foreach (['view', 'update', 'send', 'accept', 'reject', 'revert', 'discard', 'downloadPdf'] as $ability) {
        $inspection = Gate::forUser($client)->inspect($ability, $budget);

        expect($inspection->denied())->toBeTrue()->and($inspection->message())->toContain('permiso');
    }
});

it('RF-57, RF-53: admin y staff tienen los mismos permisos y nadie elimina un presupuesto', function (string $role) {
    $user = User::factory()->{$role}()->create();
    $budget = Budget::factory()->create();

    foreach (['viewAny', 'create'] as $ability) {
        expect(Gate::forUser($user)->allows($ability, Budget::class))->toBeTrue();
    }

    foreach (['view', 'update', 'send', 'accept', 'reject', 'revert', 'discard', 'downloadPdf'] as $ability) {
        expect(Gate::forUser($user)->allows($ability, $budget))->toBeTrue();
    }

    foreach (['delete', 'forceDelete'] as $ability) {
        expect(Gate::forUser($user)->denies($ability, $budget))->toBeTrue();
    }
})->with(['staff', 'admin']);

it('RF-16: la página de edición existe para la cabecera del presupuesto', function () {
    signInStaff();
    $budget = Budget::factory()->create();

    Livewire::test(EditBudget::class, ['record' => $budget->getKey()])->assertSuccessful();
});
