<?php

use App\Enums\ClientHistoryField;
use App\Enums\ClientStatus;
use App\Filament\Staff\Resources\Clients\Pages\CreateClient;
use App\Filament\Staff\Resources\Clients\Pages\EditClient;
use App\Filament\Staff\Resources\Clients\Pages\ViewClient;
use App\Filament\Staff\Resources\Clients\RelationManagers\AccountsRelationManager;
use App\Filament\Staff\Resources\Clients\RelationManagers\ContactsRelationManager;
use App\Filament\Staff\Resources\Clients\RelationManagers\HistoriesRelationManager;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientHistory;
use App\Models\Province;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    app()->setLocale('es');
    Filament::setCurrentPanel('staff');
});

function actingAsStaffMember(string $role = 'staff'): User
{
    $user = User::factory()->{$role}()->create();

    test()->actingAs($user);

    return $user;
}

function managerFor(string $manager, Client $client): Testable
{
    return Livewire::test($manager, ['ownerRecord' => $client, 'pageClass' => ViewClient::class]);
}

it('RF-1, RF-3, RF-24, RF-35: staff y admin dan de alta una persona física desde el panel', function (string $role) {
    $actor = actingAsStaffMember($role);

    Livewire::test(CreateClient::class)
        ->fillForm(['person_type' => 'individual', 'first_name' => 'Ana', 'last_name' => 'Pérez', 'document' => '12.345.678'])
        ->call('create')
        ->assertHasNoFormErrors();

    $client = Client::query()->sole();

    expect($client->display_name)->toBe('Ana Pérez')
        ->and($client->document)->toBe('12345678')
        ->and($client->refresh()->status)->toBe(ClientStatus::Active)
        ->and($client->created_by)->toBe($actor->id)
        ->and($client->histories)->toHaveCount(1);
})->with(['staff', 'admin']);

it('RF-1, RF-4: da de alta una persona jurídica con razón social y CUIT', function () {
    actingAsStaffMember();

    Livewire::test(CreateClient::class)
        ->fillForm(['person_type' => 'company', 'company_name' => 'Imprenta Sur S.A.', 'document' => '20-12345678-6'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Client::query()->sole()->document)->toBe('20123456786');
});

it('RF-6: el formulario de alta muestra el conflicto de documento indicando el cliente existente', function () {
    actingAsStaffMember();
    Client::factory()->create(['first_name' => 'Luis', 'last_name' => 'Gómez', 'document' => '12345678'])->delete();

    Livewire::test(CreateClient::class)
        ->fillForm(['person_type' => 'individual', 'first_name' => 'Ana', 'last_name' => 'Pérez', 'document' => '12345678'])
        ->call('create')
        ->assertHasFormErrors(['document'])
        ->assertSee('Ya existe un cliente con el documento 12345678 (Luis Gómez).');

    expect(Client::withTrashed()->count())->toBe(1);
});

it('RF-3: el alta exige los campos de su tipo de persona', function () {
    actingAsStaffMember();

    Livewire::test(CreateClient::class)
        ->fillForm(['person_type' => 'individual', 'first_name' => '', 'last_name' => '', 'document' => ''])
        ->call('create')
        ->assertHasFormErrors(['first_name', 'last_name', 'document']);
});

it('RF-7, RF-9, RF-12, RF-36: modifica la identificación y el domicilio de un cliente', function () {
    actingAsStaffMember();
    $province = Province::query()->create(['name' => 'Córdoba']);
    $client = Client::factory()->create(['first_name' => 'Ana', 'last_name' => 'Pérez', 'document' => '12345678']);

    Livewire::test(EditClient::class, ['record' => $client->getKey()])
        ->fillForm([
            'first_name' => 'Anabel',
            'last_name' => 'Pérez',
            'document' => '12345678',
            'street' => 'Av. Colón',
            'street_number' => '1234',
            'city' => 'Córdoba',
            'province_id' => $province->id,
            'postal_code' => 'X5000',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($client->refresh()->first_name)->toBe('Anabel')
        ->and($client->street)->toBe('Av. Colón')
        ->and($client->province_id)->toBe($province->id)
        ->and($client->histories->pluck('field')->all())->toBe([ClientHistoryField::Identification, ClientHistoryField::Address]);
});

it('RF-8: el tipo de persona no se puede cambiar al editar', function () {
    actingAsStaffMember();
    $client = Client::factory()->create();

    Livewire::test(EditClient::class, ['record' => $client->getKey()])
        ->assertFormFieldIsDisabled('person_type');
});

it('RF-25: staff marca un cliente como inactivo y lo reactiva', function () {
    actingAsStaffMember();
    $client = Client::factory()->create();

    Livewire::test(ViewClient::class, ['record' => $client->getKey()])
        ->assertActionHidden('activate')
        ->callAction('deactivate');

    expect($client->refresh()->status)->toBe(ClientStatus::Inactive);

    Livewire::test(ViewClient::class, ['record' => $client->getKey()])
        ->assertActionHidden('deactivate')
        ->callAction('activate');

    expect($client->refresh()->status)->toBe(ClientStatus::Active);
});

it('RF-28, RF-32: staff archiva un cliente y luego lo restaura, quedando inactivo', function () {
    actingAsStaffMember();
    $client = Client::factory()->create();

    Livewire::test(ViewClient::class, ['record' => $client->getKey()])
        ->assertActionHidden('restore')
        ->callAction('archive');

    expect($client->refresh()->trashed())->toBeTrue()
        ->and($client->status)->toBe(ClientStatus::Inactive);

    Livewire::test(ViewClient::class, ['record' => $client->getKey()])
        ->assertActionHidden('archive')
        ->callAction('restore');

    expect($client->refresh()->trashed())->toBeFalse()
        ->and($client->status)->toBe(ClientStatus::Inactive);
});

it('RF-34: la sección no ofrece eliminar clientes', function () {
    actingAsStaffMember();
    $client = Client::factory()->create();

    Livewire::test(EditClient::class, ['record' => $client->getKey()])
        ->assertActionDoesNotExist('delete')
        ->assertActionDoesNotExist('forceDelete');

    Livewire::test(ViewClient::class, ['record' => $client->getKey()])
        ->assertActionDoesNotExist('delete');
});

it('RF-13, RF-16: agrega contactos desde la ficha y el primero queda como principal', function () {
    actingAsStaffMember();
    $client = Client::factory()->create();

    managerFor(ContactsRelationManager::class, $client)
        ->callAction(TestAction::make('create')->table(), ['name' => 'Marta Ruiz', 'role' => 'Compras', 'phone' => '11-5555-0001', 'email' => null])
        ->assertHasNoFormErrors();

    managerFor(ContactsRelationManager::class, $client)
        ->callAction(TestAction::make('create')->table(), ['name' => 'Juan Díaz', 'phone' => null, 'email' => 'juan@example.com']);

    expect($client->contacts()->orderBy('id')->pluck('is_primary')->all())->toBe([true, false]);
});

it('RF-15: el contacto exige teléfono o email', function () {
    actingAsStaffMember();
    $client = Client::factory()->create();

    managerFor(ContactsRelationManager::class, $client)
        ->callAction(TestAction::make('create')->table(), ['name' => 'Marta Ruiz', 'phone' => null, 'email' => null])
        ->assertHasFormErrors(['phone']);

    expect($client->contacts()->count())->toBe(0);
});

it('RF-18, RF-19, RF-20: cambia el principal, modifica y quita contactos desde la ficha', function () {
    actingAsStaffMember();
    $client = Client::factory()->create();
    $first = ClientContact::factory()->for($client)->primary()->create(['name' => 'Marta Ruiz']);
    $second = ClientContact::factory()->for($client)->create(['name' => 'Juan Díaz']);

    managerFor(ContactsRelationManager::class, $client)
        ->callAction(TestAction::make('setPrimary')->table($second));

    expect($second->refresh()->is_primary)->toBeTrue()
        ->and($first->refresh()->is_primary)->toBeFalse();

    managerFor(ContactsRelationManager::class, $client)
        ->callAction(TestAction::make('edit')->table($second), ['name' => 'Juan P. Díaz', 'phone' => '11-5555-0009', 'email' => 'juan@example.com']);

    expect($second->refresh()->name)->toBe('Juan P. Díaz');

    managerFor(ContactsRelationManager::class, $client)
        ->callAction(TestAction::make('delete')->table($second));

    expect(ClientContact::query()->whereKey($second->id)->exists())->toBeFalse()
        ->and($first->refresh()->is_primary)->toBeTrue();
});

it('RF-37: el historial se consulta en orden cronológico y es de solo lectura', function () {
    actingAsStaffMember();
    $client = Client::factory()->create();
    $later = ClientHistory::factory()->for($client)->create(['created_at' => now()->subDay()]);
    $earlier = ClientHistory::factory()->for($client)->create(['created_at' => now()->subDays(3)]);

    managerFor(HistoriesRelationManager::class, $client)
        ->assertCanSeeTableRecords([$earlier, $later], inOrder: true)
        ->assertActionDoesNotExist(TestAction::make('create')->table())
        ->assertActionDoesNotExist(TestAction::make('edit')->table($later))
        ->assertActionDoesNotExist(TestAction::make('delete')->table($later));
});

it('RF-38, RF-39, RF-44: vincula cuentas con rol client a un cliente y las desvincula', function () {
    actingAsStaffMember();
    $client = Client::factory()->create();
    $first = User::factory()->client()->create();
    $second = User::factory()->client()->create();

    managerFor(AccountsRelationManager::class, $client)
        ->callAction(TestAction::make('link')->table(), ['account_id' => $first->id]);
    managerFor(AccountsRelationManager::class, $client)
        ->callAction(TestAction::make('link')->table(), ['account_id' => $second->id]);

    expect($client->accounts()->count())->toBe(2);

    managerFor(AccountsRelationManager::class, $client)
        ->assertCanSeeTableRecords([$first, $second])
        ->callAction(TestAction::make('unlink')->table($first));

    expect($first->refresh()->client_id)->toBeNull()
        ->and($second->refresh()->client_id)->toBe($client->id)
        ->and($client->histories()->where('field', ClientHistoryField::AccountLink)->count())->toBe(3);
});

it('RF-40: solo se ofrecen para vincular las cuentas client sin cliente asignado', function () {
    actingAsStaffMember();
    $client = Client::factory()->create();
    $other = Client::factory()->create();
    $free = User::factory()->client()->create();
    User::factory()->client()->create(['client_id' => $other->id]);
    User::factory()->staff()->create();

    managerFor(AccountsRelationManager::class, $client)
        ->mountAction(TestAction::make('link')->table())
        ->assertFormFieldExists('account_id', checkFieldUsing: fn ($field) => array_keys($field->getOptions()) === [$free->id]);
});

it('RF-45, RF-21: la ficha muestra identificación, domicilio, contactos, estado y teléfono de contacto', function () {
    actingAsStaffMember();
    $province = Province::query()->create(['name' => 'Córdoba']);
    $client = Client::factory()->create([
        'first_name' => 'Ana', 'last_name' => 'Pérez', 'document' => '12345678',
        'street' => 'Av. Colón', 'street_number' => '1234', 'city' => 'Córdoba', 'province_id' => $province->id,
    ]);
    ClientContact::factory()->for($client)->primary()->create(['name' => 'Marta Ruiz', 'phone' => '11-5555-0001']);

    Livewire::test(ViewClient::class, ['record' => $client->getKey()])
        ->assertSee('Ana Pérez')
        ->assertSee('12345678')
        ->assertSee('Persona física')
        ->assertSee('Av. Colón')
        ->assertSee('Córdoba')
        ->assertSee('Activo')
        ->assertSee('11-5555-0001');
});

it('RF-22: la ficha indica que el cliente no tiene teléfono de contacto', function () {
    actingAsStaffMember();
    $client = Client::factory()->create();
    ClientContact::factory()->for($client)->primary()->create(['phone' => null, 'email' => 'marta@example.com']);

    Livewire::test(ViewClient::class, ['record' => $client->getKey()])
        ->assertSee('Sin teléfono de contacto');
});

it('RF-27: la ficha de un cliente inactivo o archivado sigue consultable', function () {
    actingAsStaffMember();
    $inactive = Client::factory()->inactive()->create(['first_name' => 'Luis', 'last_name' => 'Gómez']);
    $archived = Client::factory()->archived()->create(['first_name' => 'Rosa', 'last_name' => 'Sosa']);

    Livewire::test(ViewClient::class, ['record' => $inactive->getKey()])->assertSee('Luis Gómez')->assertSee('Inactivo');
    Livewire::test(ViewClient::class, ['record' => $archived->getKey()])->assertSee('Rosa Sosa');
});

it('RF-62: una cuenta client no entra a la sección de clientes del panel de staff', function () {
    $this->actingAs(User::factory()->client()->create());

    $this->get('/staff/clients')->assertForbidden();
});

it('RF-1: la sección de clientes aparece en la navegación de staff y admin', function (string $role) {
    actingAsStaffMember($role);

    $this->get('/staff')->assertOk()->assertSee('/staff/clients');
})->with(['staff', 'admin']);
