<?php

use App\Filament\Client\Pages\ContractsPage;
use App\Models\Budget;
use App\Models\Client;
use App\Models\Contract;
use App\Models\ContractPeriod;
use App\Models\Payment;
use App\Models\PeriodConcept;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    app()->setLocale('es');
    Filament::setCurrentPanel('client');
    // 12:00 del 15 de octubre de 2026 en Argentina.
    Carbon::setTestNow(CarbonImmutable::parse('2026-10-15 15:00:00', 'UTC'));
    $this->client = Client::factory()->create(['first_name' => 'Ana', 'last_name' => 'Pérez']);
    $this->account = User::factory()->client()->create(['client_id' => $this->client->id]);
});

afterEach(fn () => Carbon::setTestNow());

function portalContract(Client $client, array $attributes = []): Contract
{
    $budget = Budget::factory()->accepted()->monthly()->create(['client_id' => $client->id]);

    return Contract::factory()->create(['budget_id' => $budget->id, ...$attributes]);
}

function contractsPage()
{
    return Livewire::test(ContractsPage::class);
}

it('RF-93: la cuenta vinculada llega a sus contratos desde el portal', function () {
    portalContract($this->client);

    $this->actingAs($this->account)->get('/portal/contracts')->assertOk()->assertSeeLivewire(ContractsPage::class);
});

it('RF-93: muestra los contratos del cliente con su estado, importe, inicio y plazo', function () {
    $active = portalContract($this->client, ['monthly_amount' => '1500.00', 'start_date' => '2026-09-01', 'term_months' => 6, 'term_end_month' => '2027-02-01']);
    $cancelled = portalContract($this->client, ['monthly_amount' => '2200.00', 'start_date' => '2025-05-10', 'status' => 'cancelled']);

    $this->actingAs($this->account);

    contractsPage()
        ->assertSee("#{$active->id}")
        ->assertSee('$ 1.500,00')
        ->assertSee('01/09/2026')
        ->assertSee('6 meses')
        ->assertSee('Vigente')
        ->assertSee("#{$cancelled->id}")
        ->assertSee('$ 2.200,00')
        ->assertSee('Indefinido')
        ->assertSee('Cancelado');
});

it('RF-93, RF-97: no muestra los contratos de otro cliente', function () {
    $other = Client::factory()->create(['first_name' => 'Luis', 'last_name' => 'Gómez']);
    $foreign = portalContract($other, ['monthly_amount' => '7777.00']);
    ContractPeriod::factory()->create(['contract_id' => $foreign->id, 'total' => '8888.00', 'fee' => '8888.00']);
    portalContract($this->client);

    $this->actingAs($this->account);

    contractsPage()
        ->assertDontSee('7.777,00')
        ->assertDontSee('8.888,00')
        ->assertDontSee('Luis Gómez');
});

it('RF-94: de cada período muestra la cuota, los extras, los descuentos con su justificación, el total, lo pagado, el saldo, el estado y el vencimiento', function () {
    $contract = portalContract($this->client);
    $period = ContractPeriod::factory()->create([
        'contract_id' => $contract->id,
        'month' => '2026-09-01',
        'due_date' => '2026-09-10',
        'fee' => '1000.00',
        'total' => '1150.00',
        'paid_amount' => '400.00',
    ]);
    PeriodConcept::factory()->create(['contract_period_id' => $period->id, 'description' => 'Banner adicional', 'amount' => '300.00']);
    PeriodConcept::factory()->discount()->create(['contract_period_id' => $period->id, 'description' => 'Bonificación por pronto pago', 'amount' => '150.00']);

    $this->actingAs($this->account);

    contractsPage()
        ->assertSee('09/2026')
        ->assertSee('10/09/2026')
        ->assertSee('$ 1.000,00')
        ->assertSee('Banner adicional')
        ->assertSee('$ 300,00')
        ->assertSee('Bonificación por pronto pago')
        ->assertSee('$ 150,00')
        ->assertSee('$ 1.150,00')
        ->assertSee('$ 400,00')
        ->assertSee('$ 750,00')
        ->assertSee('Parcial')
        ->assertSee('Vencido');
});

it('RF-94: un período al día no se muestra como vencido', function () {
    $contract = portalContract($this->client);
    ContractPeriod::factory()->create(['contract_id' => $contract->id, 'month' => '2026-10-01', 'due_date' => '2026-10-25']);

    $this->actingAs($this->account);

    contractsPage()->assertSee('Pendiente')->assertDontSee('Vencido');
});

it('RF-95: muestra el historial de pagos vigentes con fecha, importe, forma de pago y referencia', function () {
    $contract = portalContract($this->client);
    $period = ContractPeriod::factory()->create(['contract_id' => $contract->id, 'month' => '2026-09-01']);
    Payment::factory()->create([
        'contract_period_id' => $period->id,
        'paid_on' => '2026-09-12',
        'amount' => '411.00',
        'method' => 'transfer',
        'reference' => 'Transf. 998877',
    ]);

    $this->actingAs($this->account);

    contractsPage()
        ->assertSee('12/09/2026')
        ->assertSee('$ 411,00')
        ->assertSee('Transferencia')
        ->assertSee('Transf. 998877');
});

it('RF-96: no muestra los pagos anulados ni su justificación', function () {
    $contract = portalContract($this->client);
    $period = ContractPeriod::factory()->create(['contract_id' => $contract->id]);
    Payment::factory()->create(['contract_period_id' => $period->id, 'amount' => '411.00']);
    Payment::factory()->voided('Pago duplicado')->create(['contract_period_id' => $period->id, 'amount' => '999.00', 'reference' => 'Referencia anulada']);

    $this->actingAs($this->account);

    contractsPage()
        ->assertSee('$ 411,00')
        ->assertDontSee('$ 999,00')
        ->assertDontSee('Pago duplicado')
        ->assertDontSee('Referencia anulada');
});

it('RF-98: la página no ofrece ninguna acción para crear, modificar, cancelar, reactivar, anular, quitar o cargar datos', function () {
    $contract = portalContract($this->client);
    $period = ContractPeriod::factory()->create(['contract_id' => $contract->id]);
    PeriodConcept::factory()->create(['contract_period_id' => $period->id]);
    Payment::factory()->create(['contract_period_id' => $period->id, 'amount' => '100.00']);

    $this->actingAs($this->account);

    contractsPage()
        ->assertDontSee('Cancelar contrato')
        ->assertDontSee('Reactivar contrato')
        ->assertDontSee('Modificar importe')
        ->assertDontSee('Modificar día')
        ->assertDontSee('Agregar extra')
        ->assertDontSee('Agregar descuento')
        ->assertDontSee('Referencia de factura')
        ->assertDontSee('Registrar pago')
        ->assertDontSee('Anular')
        ->assertDontSee('Quitar');
});

it('RF-98: una cuenta de cliente no entra a la gestión de contratos del portal de staff', function () {
    $contract = portalContract($this->client);

    $this->actingAs($this->account);

    $this->get('/staff/contracts')->assertForbidden();
    $this->get("/staff/contracts/{$contract->id}")->assertForbidden();
    $this->get('/staff/contract-periods')->assertForbidden();
});

it('RF-93: un cliente sin contratos lo indica en la página', function () {
    $this->actingAs($this->account);

    contractsPage()->assertSee('Todavía no tiene contratos.');
});

it('RF-97: una cuenta sin vínculo con un cliente ve el aviso de que su acceso no está habilitado', function () {
    $unlinked = User::factory()->client()->create(['client_id' => null]);
    portalContract($this->client, ['monthly_amount' => '1500.00']);

    $this->actingAs($unlinked);

    contractsPage()
        ->assertSee('Su acceso todavía no fue habilitado por la agencia.')
        ->assertDontSee('1.500,00');
});

it('RF-97: la página es siempre la del cliente vinculado, aunque se pida otro desde la URL', function () {
    $other = Client::factory()->create();
    portalContract($other, ['monthly_amount' => '7777.00']);
    portalContract($this->client, ['monthly_amount' => '1500.00']);

    $this->actingAs($this->account);

    foreach (["/portal/contracts?client={$other->id}", "/portal/contracts?client_id={$other->id}"] as $url) {
        $this->get($url)->assertOk()->assertSee('1.500,00')->assertDontSee('7.777,00');
    }
});
