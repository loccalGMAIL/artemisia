<?php

use App\Enums\ContractHistoryField;
use App\Filament\Staff\Resources\Contracts\Pages\ListContracts;
use App\Filament\Staff\Resources\Contracts\Pages\ViewContract;
use App\Filament\Staff\Resources\Contracts\RelationManagers\HistoriesRelationManager;
use App\Filament\Staff\Resources\Contracts\RelationManagers\PeriodsRelationManager;
use App\Models\Budget;
use App\Models\Client;
use App\Models\Contract;
use App\Models\ContractHistory;
use App\Models\ContractPeriod;
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

function contractList()
{
    return Livewire::test(ListContracts::class);
}

function contractFor(Client $client, array $attributes = []): Contract
{
    $budget = Budget::factory()->accepted()->monthly()->create(['client_id' => $client->id]);

    return Contract::factory()->create(['budget_id' => $budget->id, ...$attributes]);
}

it('RF-81: el listado muestra cliente, importe mensual, fecha de inicio, plazo y estado', function (string $role) {
    $this->actingAs(User::factory()->{$role}()->create());
    $client = Client::factory()->create(['first_name' => 'Ana', 'last_name' => 'Pérez']);
    $fixed = contractFor($client, ['monthly_amount' => '1500.00', 'start_date' => '2026-09-01', 'term_months' => 6, 'term_end_month' => '2027-02-01']);
    $indefinite = contractFor(Client::factory()->create(['first_name' => 'Luis', 'last_name' => 'Gómez']), ['start_date' => '2026-05-10', 'status' => 'cancelled']);

    contractList()
        ->assertCanSeeTableRecords([$fixed, $indefinite])
        ->assertSee('Ana Pérez')
        ->assertSee('$ 1.500,00')
        ->assertSee('01/09/2026')
        ->assertSee('6 meses')
        ->assertSee('Vigente')
        ->assertSee('Luis Gómez')
        ->assertSee('10/05/2026')
        ->assertSee('Indefinido')
        ->assertSee('Cancelado');
})->with(['admin', 'staff']);

it('RF-81: un contrato eliminado lógicamente no aparece en el listado', function () {
    $visible = Contract::factory()->create();
    $gone = Contract::factory()->create();
    $gone->delete();

    contractList()
        ->assertCanSeeTableRecords([$visible])
        ->assertCanNotSeeTableRecords([$gone]);
});

it('RF-82: filtra el listado por cliente', function () {
    $ana = Client::factory()->create();
    $beto = Client::factory()->create();
    $ofAna = contractFor($ana);
    $ofBeto = contractFor($beto);

    contractList()
        ->filterTable('client_id', $ana->id)
        ->assertCanSeeTableRecords([$ofAna])
        ->assertCanNotSeeTableRecords([$ofBeto]);
});

it('RF-83: filtra el listado por estado', function () {
    $active = Contract::factory()->create();
    $cancelled = Contract::factory()->cancelled()->create();
    $finished = Contract::factory()->finished()->create();

    contractList()
        ->filterTable('status', 'cancelled')
        ->assertCanSeeTableRecords([$cancelled])
        ->assertCanNotSeeTableRecords([$active, $finished]);
});

it('RF-84: la ficha del contrato muestra su cliente, su importe, su vencimiento, su plazo y su estado', function () {
    $client = Client::factory()->create(['first_name' => 'Rosa', 'last_name' => 'Sosa']);
    $contract = contractFor($client, ['monthly_amount' => '2300.00', 'due_day' => 15, 'start_date' => '2026-08-01', 'term_months' => 12, 'term_end_month' => '2027-07-01']);

    Livewire::test(ViewContract::class, ['record' => $contract->getKey()])
        ->assertSee('Rosa Sosa')
        ->assertSee('$ 2.300,00')
        ->assertSee('15')
        ->assertSee('01/08/2026')
        ->assertSee('12 meses')
        ->assertSee('Vigente');
});

it('RF-84: la ficha muestra un contrato de un cliente archivado', function () {
    $client = Client::factory()->create(['first_name' => 'Rosa', 'last_name' => 'Sosa']);
    $contract = contractFor($client);
    $client->delete();

    Livewire::test(ViewContract::class, ['record' => $contract->getKey()])->assertSee('Rosa Sosa');
});

it('RF-84: la ficha lista los períodos del contrato con su mes, su vencimiento, su total y lo pagado, del más viejo al más nuevo', function () {
    $contract = Contract::factory()->create();
    $october = ContractPeriod::factory()->for($contract)->create(['month' => '2026-10-01', 'due_date' => '2026-10-10', 'total' => '1250.00', 'paid_amount' => '400.00']);
    $august = ContractPeriod::factory()->for($contract)->create(['month' => '2026-08-01', 'due_date' => '2026-08-10', 'total' => '1000.00', 'paid_amount' => '1000.00']);
    $other = ContractPeriod::factory()->create();

    Livewire::test(PeriodsRelationManager::class, ['ownerRecord' => $contract, 'pageClass' => ViewContract::class])
        ->assertCanSeeTableRecords([$august, $october], inOrder: true)
        ->assertCanNotSeeTableRecords([$other])
        ->assertSee('08/2026')
        ->assertSee('10/08/2026')
        ->assertSee('$ 1.250,00')
        ->assertSee('$ 400,00');
});

it('RF-86: el historial del contrato se muestra en orden cronológico, con el sistema como autor cuando lo hizo el sistema', function () {
    $contract = Contract::factory()->create();
    $late = ContractHistory::factory()->for($contract)->create(['field' => ContractHistoryField::StatusChanged, 'created_at' => '2026-10-03 09:00:00', 'author_id' => null]);
    $early = ContractHistory::factory()->for($contract)->create(['created_at' => '2026-10-01 09:00:00', 'author_id' => $this->actor->id]);
    ContractHistory::factory()->create();

    Livewire::test(HistoriesRelationManager::class, ['ownerRecord' => $contract, 'pageClass' => ViewContract::class])
        ->assertCanSeeTableRecords([$early, $late], inOrder: true)
        ->assertSee('Alta')
        ->assertSee('Estado')
        ->assertSee('Sistema');
});
