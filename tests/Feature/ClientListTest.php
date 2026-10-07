<?php

use App\Filament\Staff\Resources\Clients\Pages\ListClients;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    app()->setLocale('es');
    Filament::setCurrentPanel('staff');
    $this->actingAs(User::factory()->staff()->create());
});

it('RF-49: el listado muestra nombre para mostrar, tipo de persona, documento, estado y fecha de alta', function () {
    $client = Client::factory()->create(['first_name' => 'Ana', 'last_name' => 'Pérez', 'document' => '12345678']);

    Livewire::test(ListClients::class)
        ->assertCanSeeTableRecords([$client])
        ->assertCanRenderTableColumn('display_name')
        ->assertCanRenderTableColumn('person_type')
        ->assertCanRenderTableColumn('document')
        ->assertCanRenderTableColumn('status')
        ->assertCanRenderTableColumn('created_at')
        ->assertSee('Ana Pérez')
        ->assertSee('Persona física')
        ->assertSee('12345678')
        ->assertSee('Activo');
});

it('RF-50, RF-51: los archivados no aparecen por defecto y se incluyen con un filtro explícito', function () {
    $visible = Client::factory()->create();
    $archived = Client::factory()->archived()->create();

    Livewire::test(ListClients::class)
        ->assertCanSeeTableRecords([$visible])
        ->assertCanNotSeeTableRecords([$archived])
        ->filterTable('trashed', true)
        ->assertCanSeeTableRecords([$visible, $archived])
        ->removeTableFilter('trashed')
        ->assertCanNotSeeTableRecords([$archived]);
});

it('RF-52: busca sin distinguir mayúsculas en el nombre para mostrar', function (string $term) {
    $ana = Client::factory()->create(['first_name' => 'Ana', 'last_name' => 'Pérez']);
    $imprenta = Client::factory()->company()->create(['company_name' => 'Imprenta Sur S.A.']);
    $luis = Client::factory()->create(['first_name' => 'Luis', 'last_name' => 'Gómez']);

    $expected = str_contains(strtolower($term), 'imprenta') ? [$imprenta] : [$ana];

    Livewire::test(ListClients::class)
        ->searchTable($term)
        ->assertCanSeeTableRecords($expected)
        ->assertCanNotSeeTableRecords(collect([$ana, $imprenta, $luis])->reject(fn ($client) => $expected[0]->is($client))->all());
})->with([
    'nombre en minúsculas' => 'ana',
    'nombre en mayúsculas' => 'ANA',
    'nombre y apellido' => 'ana pér',
    'razón social parcial' => 'imprenta s',
    'razón social en mayúsculas' => 'IMPRENTA',
]);

it('RF-52: busca por documento, con o sin puntos y guiones', function (string $term) {
    $target = Client::factory()->create(['document' => '12345678']);
    $other = Client::factory()->create(['document' => '87654321']);

    Livewire::test(ListClients::class)
        ->searchTable($term)
        ->assertCanSeeTableRecords([$target])
        ->assertCanNotSeeTableRecords([$other]);
})->with([
    'completo' => '12345678',
    'parcial' => '3456',
    'con puntos' => '12.345.678',
]);

it('RF-52: busca por el teléfono o el email de alguno de los contactos', function (string $term) {
    $target = Client::factory()->create();
    $other = Client::factory()->create();
    ClientContact::factory()->for($target)->primary()->create(['phone' => '11-5555-0001', 'email' => 'marta@example.com']);
    ClientContact::factory()->for($target)->create(['phone' => '351-444-9999', 'email' => 'Juan.Perez@Example.com']);
    ClientContact::factory()->for($other)->primary()->create(['phone' => '11-7777-0000', 'email' => 'otro@example.com']);

    Livewire::test(ListClients::class)
        ->searchTable($term)
        ->assertCanSeeTableRecords([$target])
        ->assertCanNotSeeTableRecords([$other]);
})->with([
    'teléfono del principal' => '5555-0001',
    'teléfono de otro contacto' => '351-444',
    'email' => 'marta@',
    'email en otras mayúsculas' => 'JUAN.PEREZ',
]);

it('RF-52: los comodines de SQL escritos en la búsqueda se toman literalmente', function () {
    $client = Client::factory()->create(['first_name' => 'Ana', 'last_name' => 'Pérez']);

    Livewire::test(ListClients::class)
        ->searchTable('%')
        ->assertCanNotSeeTableRecords([$client]);
});

it('RF-53: filtra por estado y por tipo de persona', function () {
    $activeIndividual = Client::factory()->create();
    $inactiveIndividual = Client::factory()->inactive()->create();
    $activeCompany = Client::factory()->company()->create();

    Livewire::test(ListClients::class)
        ->filterTable('status', 'inactive')
        ->assertCanSeeTableRecords([$inactiveIndividual])
        ->assertCanNotSeeTableRecords([$activeIndividual, $activeCompany]);

    Livewire::test(ListClients::class)
        ->filterTable('person_type', 'company')
        ->assertCanSeeTableRecords([$activeCompany])
        ->assertCanNotSeeTableRecords([$activeIndividual, $inactiveIndividual]);
});

it('RF-52, RF-53: combina búsqueda y filtros', function () {
    $match = Client::factory()->create(['first_name' => 'Ana', 'last_name' => 'Pérez']);
    $inactive = Client::factory()->inactive()->create(['first_name' => 'Ana', 'last_name' => 'Gómez']);

    Livewire::test(ListClients::class)
        ->searchTable('ana')
        ->filterTable('status', 'active')
        ->assertCanSeeTableRecords([$match])
        ->assertCanNotSeeTableRecords([$inactive]);
});

it('RF-54: ordena por nombre para mostrar, de personas físicas y razones sociales', function () {
    $zeta = Client::factory()->create(['first_name' => 'Zoe', 'last_name' => 'Álvarez']);
    $beta = Client::factory()->company()->create(['company_name' => 'Beta S.A.']);
    $alfa = Client::factory()->create(['first_name' => 'Alicia', 'last_name' => 'Zárate']);

    Livewire::test(ListClients::class)
        ->sortTable('display_name')
        ->assertCanSeeTableRecords([$alfa, $beta, $zeta], inOrder: true)
        ->sortTable('display_name', 'desc')
        ->assertCanSeeTableRecords([$zeta, $beta, $alfa], inOrder: true);
});

it('RF-54: ordena por fecha de alta', function () {
    $oldest = Client::factory()->create(['created_at' => now()->subDays(10)]);
    $middle = Client::factory()->create(['created_at' => now()->subDays(5)]);
    $newest = Client::factory()->create(['created_at' => now()->subDay()]);

    Livewire::test(ListClients::class)
        ->sortTable('created_at')
        ->assertCanSeeTableRecords([$oldest, $middle, $newest], inOrder: true)
        ->sortTable('created_at', 'desc')
        ->assertCanSeeTableRecords([$newest, $middle, $oldest], inOrder: true);
});

it('RF-55: el listado está paginado', function () {
    Client::factory()->count(30)->create();

    Livewire::test(ListClients::class)
        ->assertCountTableRecords(25)
        ->call('gotoPage', 2)
        ->assertCountTableRecords(5);
});
