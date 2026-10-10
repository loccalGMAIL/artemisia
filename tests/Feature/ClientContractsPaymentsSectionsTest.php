<?php

use App\Filament\Staff\Resources\Clients\ClientCardExtensions;
use App\Filament\Staff\Resources\Clients\Pages\ViewClient;
use App\Filament\Staff\Resources\Contracts\ClientContractsSection;
use App\Filament\Staff\Resources\Contracts\ClientPaymentsSection;
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

function contractOf(Client $client, array $attributes = []): Contract
{
    $budget = Budget::factory()->accepted()->monthly()->create(['client_id' => $client->id]);

    return Contract::factory()->create(['budget_id' => $budget->id, ...$attributes]);
}

function clientCard(Client $client)
{
    return Livewire::test(ViewClient::class, ['record' => $client->getKey()]);
}

it('RF-91, RF-92: el módulo de contratos registra sus dos secciones en la ficha del cliente', function () {
    ClientContractsSection::register();
    ClientPaymentsSection::register();

    expect(ClientCardExtensions::registered())->toBe(['contracts', 'payments']);
});

it('RF-91: la ficha muestra los contratos del cliente con importe, inicio, plazo y estado', function () {
    ClientContractsSection::register();

    $client = Client::factory()->create();
    $other = Client::factory()->create(['first_name' => 'Otro', 'last_name' => 'Cliente']);
    $fixed = contractOf($client, ['monthly_amount' => '1500.00', 'start_date' => '2026-09-01', 'term_months' => 6, 'term_end_month' => '2027-02-01']);
    $cancelled = contractOf($client, ['monthly_amount' => '2200.00', 'start_date' => '2025-05-10', 'status' => 'cancelled']);
    contractOf($other, ['monthly_amount' => '9999.00']);

    clientCard($client)
        ->assertSee('Contratos')
        ->assertSee("#{$fixed->id}")
        ->assertSee('1.500,00')
        ->assertSee('01/09/2026')
        ->assertSee('6 meses')
        ->assertSee('Vigente')
        ->assertSee("#{$cancelled->id}")
        ->assertSee('2.200,00')
        ->assertSee('10/05/2025')
        ->assertSee('Indefinido')
        ->assertSee('Cancelado')
        ->assertDontSee('9.999,00');
});

it('RF-91: cada contrato de la sección lleva a su ficha', function () {
    ClientContractsSection::register();

    $client = Client::factory()->create();
    $contract = contractOf($client);

    clientCard($client)->assertSee(route('filament.staff.resources.contracts.view', ['record' => $contract]));
});

it('RF-91: un cliente sin contratos lo indica en la ficha', function () {
    ClientContractsSection::register();

    clientCard(Client::factory()->create())
        ->assertSee('Contratos')
        ->assertSee('Este cliente todavía no tiene contratos.');
});

it('RF-92: la ficha muestra el historial de pagos del cliente, los vigentes y los anulados', function () {
    ClientPaymentsSection::register();

    $client = Client::factory()->create();
    $contract = contractOf($client);
    $period = ContractPeriod::factory()->create(['contract_id' => $contract->id, 'month' => '2026-09-01']);
    Payment::factory()->create(['contract_period_id' => $period->id, 'paid_on' => '2026-09-12', 'amount' => '400.00', 'reference' => 'Transf. 4455']);
    Payment::factory()->voided('Pago duplicado')->create(['contract_period_id' => $period->id, 'paid_on' => '2026-09-13', 'amount' => '250.00']);

    clientCard($client)
        ->assertSee('Pagos')
        ->assertSee('12/09/2026')
        ->assertSee('$ 400,00')
        ->assertSee('Transf. 4455')
        ->assertSee('09/2026')
        ->assertSee('Vigente')
        ->assertSee('13/09/2026')
        ->assertSee('$ 250,00')
        ->assertSee('Anulado')
        ->assertSee('Pago duplicado');
});

it('RF-92: no muestra los pagos de otro cliente', function () {
    ClientPaymentsSection::register();

    $client = Client::factory()->create();
    $other = Client::factory()->create();
    $period = ContractPeriod::factory()->create(['contract_id' => contractOf($other)->id]);
    Payment::factory()->create(['contract_period_id' => $period->id, 'amount' => '7777.00']);

    clientCard($client)->assertDontSee('7.777,00');
});

it('RF-92: muestra los pagos de un contrato cancelado', function () {
    ClientPaymentsSection::register();

    $client = Client::factory()->create();
    $period = ContractPeriod::factory()->create(['contract_id' => contractOf($client, ['status' => 'cancelled'])->id]);
    Payment::factory()->create(['contract_period_id' => $period->id, 'amount' => '321.00']);

    clientCard($client)->assertSee('$ 321,00');
});

it('RF-92: un cliente sin pagos lo indica en la ficha', function () {
    ClientPaymentsSection::register();

    clientCard(Client::factory()->create())
        ->assertSee('Pagos')
        ->assertSee('Este cliente todavía no tiene pagos.');
});

it('RF-91, RF-92: con las dos secciones registradas la ficha muestra presupuestos aparte de contratos y pagos', function () {
    ClientContractsSection::register();
    ClientPaymentsSection::register();

    clientCard(Client::factory()->create())
        ->assertSee('Contratos')
        ->assertSee('Pagos');
});

it('RF-91, RF-92: la aplicación registra las secciones al arrancar', function () {
    ClientCardExtensions::flush();

    // Arrancar de nuevo el proveedor vuelve a registrar lo que el módulo aporta.
    app()->getProvider(AppServiceProvider::class)->boot();

    expect(ClientCardExtensions::registered())->toContain('contracts', 'payments');
});
