<?php

use App\Filament\Staff\Resources\Accounts\AccountResource;
use App\Filament\Staff\Resources\Budgets\BudgetResource;
use App\Filament\Staff\Resources\Clients\ClientCardExtensions;
use App\Filament\Staff\Resources\Clients\ClientResource;
use App\Filament\Staff\Resources\Clients\Pages\ViewClient;
use App\Filament\Staff\Resources\Clients\RelationManagers\AccountsRelationManager;
use App\Filament\Staff\Resources\Clients\RelationManagers\BudgetsRelationManager;
use App\Filament\Staff\Resources\Clients\RelationManagers\ContactsRelationManager;
use App\Filament\Staff\Resources\Clients\RelationManagers\ContractsRelationManager;
use App\Filament\Staff\Resources\Clients\RelationManagers\HistoriesRelationManager;
use App\Filament\Staff\Resources\Clients\RelationManagers\PaymentsRelationManager;
use App\Filament\Staff\Resources\Contracts\ContractResource;
use App\Filament\Staff\Resources\Services\ServiceResource;
use App\Models\Budget;
use App\Models\Client;
use App\Models\Contract;
use App\Models\ContractPeriod;
use App\Models\Payment;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    app()->setLocale('es');
    Filament::setCurrentPanel('staff');
    $this->actingAs(User::factory()->staff()->create());
    ClientCardExtensions::flush();
});

afterEach(function () {
    ClientCardExtensions::flush();
});

function tabClient(): Client
{
    return Client::factory()->create(['first_name' => 'Ana', 'last_name' => 'Pérez']);
}

function tabContract(Client $client, array $attributes = []): Contract
{
    $budget = Budget::factory()->accepted()->monthly()->create(['client_id' => $client->id]);

    return Contract::factory()->create(['budget_id' => $budget->id, ...$attributes]);
}

function relationTab(string $manager, Client $client)
{
    return Livewire::test($manager, ['ownerRecord' => $client, 'pageClass' => ViewClient::class]);
}

it('las cuentas y los servicios van en la sección Configuración del menú', function () {
    expect(AccountResource::getNavigationGroup())->toBe('Configuración')
        ->and(ServiceResource::getNavigationGroup())->toBe('Configuración');
});

it('los clientes, los presupuestos y los contratos quedan fuera de la sección Configuración', function () {
    expect(ClientResource::getNavigationGroup())->toBeNull()
        ->and(BudgetResource::getNavigationGroup())->toBeNull()
        ->and(ContractResource::getNavigationGroup())->toBeNull();
});

it('la ficha del cliente muestra el nombre del cliente en el título en lugar de «Ver cliente»', function () {
    $client = tabClient();

    $page = Livewire::test(ViewClient::class, ['record' => $client->getKey()]);

    expect($page->instance()->getTitle())->toBe('Ana Pérez');

    $page->assertSee('Ana Pérez')->assertDontSee('Ver Cliente')->assertDontSee('Ver cliente');
});

it('la ficha del cliente muestra el nombre de una empresa en el título', function () {
    $client = Client::factory()->company()->create(['company_name' => 'Estudio Aurora S.R.L.']);

    expect(Livewire::test(ViewClient::class, ['record' => $client->getKey()])->instance()->getTitle())
        ->toBe('Estudio Aurora S.R.L.');
});

it('debajo de los datos del cliente hay pestañas: presupuestos, contratos, pagos, usuarios, contactos e historial', function () {
    expect(ClientResource::getRelations())->toBe([
        BudgetsRelationManager::class,
        ContractsRelationManager::class,
        PaymentsRelationManager::class,
        AccountsRelationManager::class,
        ContactsRelationManager::class,
        HistoriesRelationManager::class,
    ]);

    $client = tabClient();

    Livewire::test(ViewClient::class, ['record' => $client->getKey()])
        ->assertSee('Presupuestos')
        ->assertSee('Contratos')
        ->assertSee('Pagos')
        ->assertSee('Usuarios')
        ->assertSee('Contactos')
        ->assertSee('Historial');
});

it('la pestaña de usuarios es la de las cuentas vinculadas al cliente', function () {
    $client = tabClient();
    $account = User::factory()->client()->create(['client_id' => $client->id, 'name' => 'Cuenta de Ana', 'email' => 'ana@example.com']);
    User::factory()->client()->create(['name' => 'Cuenta ajena']);

    expect(AccountsRelationManager::getTitle($client, ViewClient::class))->toBe('Usuarios');

    relationTab(AccountsRelationManager::class, $client)
        ->assertCanSeeTableRecords([$account])
        ->assertSee('ana@example.com')
        ->assertDontSee('Cuenta ajena');
});

it('RF-46 (003): la pestaña de presupuestos muestra los del cliente con número, título, total, estado y emisión', function () {
    $client = tabClient();
    $other = Client::factory()->create();
    $budget = Budget::factory()->sent()->create([
        'client_id' => $client->id,
        'title' => 'Campaña de otoño',
        'issue_date' => '2026-03-15',
        'subtotal' => '1500.00',
        'total' => '1500.00',
    ]);
    $foreign = Budget::factory()->create(['client_id' => $other->id, 'title' => 'Ajeno al cliente']);

    relationTab(BudgetsRelationManager::class, $client)
        ->assertCanSeeTableRecords([$budget])
        ->assertCanNotSeeTableRecords([$foreign])
        ->assertSee("#{$budget->id}")
        ->assertSee('Campaña de otoño')
        ->assertSee('Enviado')
        ->assertSee('15/03/2026')
        ->assertSee('$ 1.500,00')
        ->assertSee(BudgetResource::getUrl('view', ['record' => $budget]), false);
});

it('RF-46 (003): la pestaña de presupuestos de un cliente sin presupuestos lo indica', function () {
    relationTab(BudgetsRelationManager::class, tabClient())->assertSee('Este cliente todavía no tiene presupuestos.');
});

it('RF-91: la pestaña de contratos muestra los del cliente con importe, inicio, plazo y estado', function () {
    $client = tabClient();
    $fixed = tabContract($client, ['monthly_amount' => '1500.00', 'start_date' => '2026-09-01', 'term_months' => 6, 'term_end_month' => '2027-02-01']);
    $cancelled = tabContract($client, ['monthly_amount' => '2200.00', 'start_date' => '2025-05-10', 'status' => 'cancelled']);
    $foreign = tabContract(Client::factory()->create(), ['monthly_amount' => '9999.00']);

    relationTab(ContractsRelationManager::class, $client)
        ->assertCanSeeTableRecords([$fixed, $cancelled])
        ->assertCanNotSeeTableRecords([$foreign])
        ->assertSee("#{$fixed->id}")
        ->assertSee('$ 1.500,00')
        ->assertSee('01/09/2026')
        ->assertSee('6 meses')
        ->assertSee('Vigente')
        ->assertSee('$ 2.200,00')
        ->assertSee('Indefinido')
        ->assertSee('Cancelado')
        ->assertSee(ContractResource::getUrl('view', ['record' => $fixed]), false);
});

it('RF-91: la pestaña de contratos de un cliente sin contratos lo indica', function () {
    relationTab(ContractsRelationManager::class, tabClient())->assertSee('Este cliente todavía no tiene contratos.');
});

it('RF-92: la pestaña de pagos muestra los pagos del cliente, vigentes y anulados, con la justificación de la anulación', function () {
    $client = tabClient();
    $period = ContractPeriod::factory()->create(['contract_id' => tabContract($client)->id, 'month' => '2026-09-01']);
    $valid = Payment::factory()->create(['contract_period_id' => $period->id, 'paid_on' => '2026-09-12', 'amount' => '400.00', 'reference' => 'Transf. 4455']);
    $voided = Payment::factory()->voided('Pago duplicado')->create(['contract_period_id' => $period->id, 'paid_on' => '2026-09-13', 'amount' => '250.00']);
    $foreignPeriod = ContractPeriod::factory()->create(['contract_id' => tabContract(Client::factory()->create())->id]);
    $foreign = Payment::factory()->create(['contract_period_id' => $foreignPeriod->id, 'amount' => '7777.00']);

    relationTab(PaymentsRelationManager::class, $client)
        ->assertCanSeeTableRecords([$voided, $valid], inOrder: true)
        ->assertCanNotSeeTableRecords([$foreign])
        ->assertSee('12/09/2026')
        ->assertSee('$ 400,00')
        ->assertSee('Transf. 4455')
        ->assertSee('09/2026')
        ->assertSee('Vigente')
        ->assertSee('$ 250,00')
        ->assertSee('Anulado')
        ->assertSee('Pago duplicado');
});

it('RF-92: la pestaña de pagos de un cliente sin pagos lo indica', function () {
    relationTab(PaymentsRelationManager::class, tabClient())->assertSee('Este cliente todavía no tiene pagos.');
});

it('RF-91, RF-92: los datos del cliente no repiten las listas que ahora están en las pestañas', function () {
    $client = tabClient();
    tabContract($client, ['monthly_amount' => '1500.00']);

    // Con la aplicación recién arrancada tampoco hay secciones sueltas de estos módulos.
    ClientCardExtensions::flush();
    app()->getProvider(AppServiceProvider::class)->boot();

    expect(ClientCardExtensions::registered())->not->toContain('budgets', 'contracts', 'payments');
});
