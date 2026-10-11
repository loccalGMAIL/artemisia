<?php

use App\Enums\PeriodHistoryField;
use App\Filament\Staff\Resources\ContractPeriods\Pages\ListContractPeriods;
use App\Filament\Staff\Resources\ContractPeriods\Pages\ViewContractPeriod;
use App\Filament\Staff\Resources\ContractPeriods\RelationManagers\ConceptsRelationManager;
use App\Filament\Staff\Resources\ContractPeriods\RelationManagers\HistoriesRelationManager;
use App\Filament\Staff\Resources\ContractPeriods\RelationManagers\PaymentsRelationManager;
use App\Models\Budget;
use App\Models\Client;
use App\Models\Contract;
use App\Models\ContractPeriod;
use App\Models\Payment;
use App\Models\PeriodConcept;
use App\Models\PeriodHistory;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    app()->setLocale('es');
    Filament::setCurrentPanel('staff');
    Carbon::setTestNow(CarbonImmutable::parse('2026-10-15 15:00:00', 'UTC'));
    $this->actor = User::factory()->staff()->create();
    $this->actingAs($this->actor);
});

afterEach(fn () => Carbon::setTestNow());

function periodList()
{
    return Livewire::test(ListContractPeriods::class);
}

function periodOfClient(Client $client, array $attributes = []): ContractPeriod
{
    $budget = Budget::factory()->accepted()->monthly()->create(['client_id' => $client->id]);
    $contract = Contract::factory()->create(['budget_id' => $budget->id]);

    return ContractPeriod::factory()->create([
        'contract_id' => $contract->id,
        'due_date' => '2026-10-25',
        ...$attributes,
    ]);
}

function viewPeriod(ContractPeriod $period)
{
    return Livewire::test(ViewContractPeriod::class, ['record' => $period->getKey()]);
}

it('RF-87: el listado muestra cliente, contrato, mes, vencimiento, total, pagado, saldo y estado de pago', function (string $role) {
    $this->actingAs(User::factory()->{$role}()->create());
    $client = Client::factory()->create(['first_name' => 'Ana', 'last_name' => 'Pérez']);
    $period = periodOfClient($client, [
        'month' => '2026-10-01',
        'due_date' => '2026-10-25',
        'fee' => '1250.00',
        'total' => '1250.00',
        'paid_amount' => '400.00',
    ]);

    periodList()
        ->assertCanSeeTableRecords([$period])
        ->assertSee('Ana Pérez')
        ->assertSee('#'.$period->contract_id)
        ->assertSee('10/2026')
        ->assertSee('25/10/2026')
        ->assertSee('$ 1.250,00')
        ->assertSee('$ 400,00')
        ->assertSee('$ 850,00')
        ->assertSee('Parcial');
})->with(['admin', 'staff']);

it('RF-87: muestra pendiente y pagado según el estado de pago de cada período', function () {
    $pending = periodOfClient(Client::factory()->create(['first_name' => 'Pía', 'last_name' => 'Pendiente']));
    $paid = periodOfClient(Client::factory()->create(['first_name' => 'Pablo', 'last_name' => 'Pagado']), ['total' => '1000.00', 'paid_amount' => '1000.00']);

    periodList()
        ->assertCanSeeTableRecords([$pending, $paid])
        ->assertSeeInOrder(['Pendiente'])
        ->assertSee('Pagado');
});

it('RF-87: muestra el período de un cliente archivado', function () {
    $client = Client::factory()->create(['first_name' => 'Rosa', 'last_name' => 'Sosa']);
    $period = periodOfClient($client);
    $client->delete();

    periodList()->assertCanSeeTableRecords([$period])->assertSee('Rosa Sosa');
});

it('RF-88: filtra el listado por cliente', function () {
    $ana = Client::factory()->create();
    $beto = Client::factory()->create();
    $ofAna = periodOfClient($ana);
    $ofBeto = periodOfClient($beto);

    periodList()
        ->filterTable('client_id', $ana->id)
        ->assertCanSeeTableRecords([$ofAna])
        ->assertCanNotSeeTableRecords([$ofBeto]);
});

it('RF-89: filtra el listado por estado de pago', function () {
    $pending = periodOfClient(Client::factory()->create());
    $partial = periodOfClient(Client::factory()->create(), ['paid_amount' => '300.00']);
    $paid = periodOfClient(Client::factory()->create(), ['paid_amount' => '1000.00']);

    periodList()
        ->filterTable('payment_status', 'pending')
        ->assertCanSeeTableRecords([$pending])
        ->assertCanNotSeeTableRecords([$partial, $paid]);

    periodList()
        ->filterTable('payment_status', 'partial')
        ->assertCanSeeTableRecords([$partial])
        ->assertCanNotSeeTableRecords([$pending, $paid]);

    periodList()
        ->filterTable('payment_status', 'paid')
        ->assertCanSeeTableRecords([$paid])
        ->assertCanNotSeeTableRecords([$pending, $partial]);
});

it('RF-74, RF-90: señala los vencidos y permite filtrarlos', function () {
    $overdue = periodOfClient(Client::factory()->create(['first_name' => 'Vera', 'last_name' => 'Vencida']), ['due_date' => '2026-10-10']);
    $current = periodOfClient(Client::factory()->create(['first_name' => 'Carlos', 'last_name' => 'Corriente']), ['due_date' => '2026-10-25']);
    $paidLate = periodOfClient(Client::factory()->create(['first_name' => 'Paula', 'last_name' => 'Pagada']), ['due_date' => '2026-10-10', 'paid_amount' => '1000.00']);

    periodList()
        ->assertCanSeeTableRecords([$overdue, $current, $paidLate])
        ->assertSee('Vencido')
        ->filterTable('overdue', true)
        ->assertCanSeeTableRecords([$overdue])
        ->assertCanNotSeeTableRecords([$current, $paidLate]);
});

it('RF-74: un vencido conserva su estado de pago', function () {
    $overdue = periodOfClient(Client::factory()->create(), ['due_date' => '2026-10-10', 'paid_amount' => '300.00']);

    periodList()->assertSee('Vencido')->assertSee('Parcial');
});

it('RF-85: la ficha muestra la cuota, el total, la referencia de factura y el estado del período', function () {
    $client = Client::factory()->create(['first_name' => 'Rosa', 'last_name' => 'Sosa']);
    $period = periodOfClient($client, [
        'month' => '2026-09-01',
        'due_date' => '2026-09-10',
        'fee' => '1000.00',
        'total' => '1150.00',
        'paid_amount' => '500.00',
        'invoice_number' => '0001-00000123',
        'invoice_date' => '2026-09-05',
    ]);

    viewPeriod($period)
        ->assertSee('Rosa Sosa')
        ->assertSee('09/2026')
        ->assertSee('10/09/2026')
        ->assertSee('$ 1.000,00')
        ->assertSee('$ 1.150,00')
        ->assertSee('$ 500,00')
        ->assertSee('$ 650,00')
        ->assertSee('0001-00000123')
        ->assertSee('05/09/2026')
        ->assertSee('Parcial')
        ->assertSee('Vencido');
});

it('RF-85: la ficha de un período sin factura no muestra una referencia', function () {
    $period = periodOfClient(Client::factory()->create());

    viewPeriod($period)->assertDontSee('0001-');
});

it('RF-85: la ficha lista los extras y los descuentos con su descripción o justificación', function () {
    $period = ContractPeriod::factory()->create();
    $extra = PeriodConcept::factory()->create(['contract_period_id' => $period->id, 'description' => 'Banner adicional', 'amount' => '300.00']);
    $discount = PeriodConcept::factory()->discount()->create(['contract_period_id' => $period->id, 'description' => 'Bonificación por pronto pago', 'amount' => '100.00']);
    $other = PeriodConcept::factory()->create();

    Livewire::test(ConceptsRelationManager::class, ['ownerRecord' => $period, 'pageClass' => ViewContractPeriod::class])
        ->assertCanSeeTableRecords([$extra, $discount], inOrder: true)
        ->assertCanNotSeeTableRecords([$other])
        ->assertSee('Extra')
        ->assertSee('Banner adicional')
        ->assertSee('$ 300,00')
        ->assertSee('Descuento')
        ->assertSee('Bonificación por pronto pago')
        ->assertSee('$ 100,00');
});

it('RF-85: la ficha lista los pagos vigentes y los anulados, con la justificación de la anulación', function () {
    $period = ContractPeriod::factory()->create();
    $valid = Payment::factory()->create(['contract_period_id' => $period->id, 'amount' => '400.00', 'paid_on' => '2026-10-02', 'reference' => 'Transf. 998877']);
    $voided = Payment::factory()->voided('Pago cargado dos veces')->create(['contract_period_id' => $period->id, 'amount' => '250.00', 'paid_on' => '2026-10-03']);
    $other = Payment::factory()->create();

    Livewire::test(PaymentsRelationManager::class, ['ownerRecord' => $period, 'pageClass' => ViewContractPeriod::class])
        ->assertCanSeeTableRecords([$valid, $voided], inOrder: true)
        ->assertCanNotSeeTableRecords([$other])
        ->assertSee('02/10/2026')
        ->assertSee('$ 400,00')
        ->assertSee('Transf. 998877')
        ->assertSee('Vigente')
        ->assertSee('$ 250,00')
        ->assertSee('Anulado')
        ->assertSee('Pago cargado dos veces');
});

it('RF-86: el historial del período se muestra en orden cronológico, con el sistema como autor cuando lo hizo el sistema', function () {
    $period = ContractPeriod::factory()->create();
    $late = PeriodHistory::factory()->create([
        'contract_period_id' => $period->id,
        'field' => PeriodHistoryField::ConceptAdded,
        'created_at' => '2026-10-03 09:00:00',
        'author_id' => $this->actor->id,
    ]);
    $early = PeriodHistory::factory()->create([
        'contract_period_id' => $period->id,
        'field' => PeriodHistoryField::Generated,
        'created_at' => '2026-10-01 00:10:00',
        'author_id' => null,
    ]);
    PeriodHistory::factory()->create();

    Livewire::test(HistoriesRelationManager::class, ['ownerRecord' => $period, 'pageClass' => ViewContractPeriod::class])
        ->assertCanSeeTableRecords([$early, $late], inOrder: true)
        ->assertSee('Período generado')
        ->assertSee('Concepto agregado')
        ->assertSee('Sistema')
        ->assertSee($this->actor->name);
});
