<?php

use App\Enums\ClientHistoryField;
use App\Enums\ClientStatus;
use App\Filament\Client\Pages\ClientProfilePage;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Province;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    app()->setLocale('es');
    Filament::setCurrentPanel('client');
    $this->client = Client::factory()->create([
        'first_name' => 'Ana', 'last_name' => 'Pérez', 'document' => '12345678',
    ]);
    $this->account = User::factory()->client()->create(['client_id' => $this->client->id]);
    $this->actingAs($this->account);
});

it('RF-59, RF-61: la cuenta vinculada modifica el domicilio y queda registrada como autora', function () {
    $province = Province::query()->create(['name' => 'Córdoba']);

    Livewire::test(ClientProfilePage::class)
        ->callAction('editAddress', [
            'street' => 'Av. Colón',
            'street_number' => '1234',
            'city' => 'Córdoba',
            'province_id' => $province->id,
            'postal_code' => 'X5000',
        ])
        ->assertHasNoFormErrors();

    expect($this->client->refresh()->street)->toBe('Av. Colón')
        ->and($this->client->province_id)->toBe($province->id);

    $history = $this->client->histories()->sole();

    expect($history->field)->toBe(ClientHistoryField::Address)
        ->and($history->author_id)->toBe($this->account->id);
});

it('RF-59, RF-61: la cuenta vinculada agrega, modifica, cambia el principal y quita contactos', function () {
    $page = fn () => Livewire::test(ClientProfilePage::class);

    $page()->callAction('addContact', ['name' => 'Marta Ruiz', 'role' => 'Compras', 'phone' => '11-5555-0001', 'email' => null])
        ->assertHasNoFormErrors();
    $page()->callAction('addContact', ['name' => 'Juan Díaz', 'phone' => null, 'email' => 'juan@example.com'])
        ->assertHasNoFormErrors();

    [$first, $second] = $this->client->contacts()->orderBy('id')->get()->all();

    expect($first->is_primary)->toBeTrue()
        ->and($second->is_primary)->toBeFalse();

    $page()->callAction(TestAction::make('editContact')->arguments(['contact' => $second->id]), [
        'name' => 'Juan P. Díaz', 'phone' => '11-5555-0009', 'email' => 'juan@example.com',
    ])->assertHasNoFormErrors();

    expect($second->refresh()->name)->toBe('Juan P. Díaz');

    $page()->callAction(TestAction::make('setPrimaryContact')->arguments(['contact' => $second->id]));

    expect($second->refresh()->is_primary)->toBeTrue()
        ->and($first->refresh()->is_primary)->toBeFalse();

    $page()->callAction(TestAction::make('removeContact')->arguments(['contact' => $second->id]));

    expect(ClientContact::query()->whereKey($second->id)->exists())->toBeFalse()
        ->and($first->refresh()->is_primary)->toBeTrue();

    $authors = $this->client->histories()->pluck('author_id')->unique()->all();

    expect($authors)->toBe([$this->account->id])
        ->and($this->client->histories()->count())->toBe(5);
});

it('RF-15, RNF-4: los formularios del portal muestran las reglas de contactos', function () {
    Livewire::test(ClientProfilePage::class)
        ->callAction('addContact', ['name' => 'Marta Ruiz', 'phone' => null, 'email' => null])
        ->assertHasFormErrors(['phone']);

    foreach (range(1, 10) as $number) {
        ClientContact::factory()->for($this->client)->create(['is_primary' => $number === 1]);
    }

    Livewire::test(ClientProfilePage::class)
        ->callAction('addContact', ['name' => 'Contacto 11', 'phone' => '11-0000-0000', 'email' => null])
        ->assertSee('hasta 10 contactos');

    expect($this->client->contacts()->count())->toBe(10);
});

it('RF-60: la cuenta no puede modificar la identificación, el estado ni el archivado', function (string $action) {
    Livewire::test(ClientProfilePage::class)
        ->assertActionDoesNotExist($action);
})->with(['editIdentification', 'deactivate', 'activate', 'archive', 'restore', 'delete']);

it('RF-60: el formulario del domicilio no incluye campos de identificación ni de estado', function () {
    $page = Livewire::test(ClientProfilePage::class)->mountAction('editAddress');

    foreach (['document', 'first_name', 'last_name', 'company_name', 'person_type', 'status'] as $field) {
        $page->assertFormFieldDoesNotExist($field);
    }

    expect($this->client->refresh()->document)->toBe('12345678')
        ->and($this->client->status)->toBe(ClientStatus::Active);
});

it('RF-62: no puede operar sobre un contacto de otro cliente aunque manipule el argumento', function () {
    $other = Client::factory()->create();
    $foreign = ClientContact::factory()->for($other)->primary()->create(['name' => 'Contacto Ajeno', 'phone' => '11-1111-1111']);

    foreach (['editContact', 'setPrimaryContact', 'removeContact'] as $action) {
        try {
            Livewire::test(ClientProfilePage::class)
                ->callAction(TestAction::make($action)->arguments(['contact' => $foreign->id]), [
                    'name' => 'Hackeado', 'phone' => '11-9999-9999', 'email' => null,
                ]);
        } catch (Throwable) {
            // Rejecting the call is the expected outcome.
        }
    }

    expect($foreign->refresh()->name)->toBe('Contacto Ajeno')
        ->and($foreign->phone)->toBe('11-1111-1111')
        ->and($foreign->is_primary)->toBeTrue()
        ->and(ClientContact::query()->whereKey($foreign->id)->exists())->toBeTrue()
        ->and($other->histories()->count())->toBe(0);
});

it('RF-58, RF-59: con el cliente archivado la cuenta no puede modificar nada', function () {
    $this->client->delete();

    $page = Livewire::test(ClientProfilePage::class)
        ->assertActionHidden('editAddress')
        ->assertActionHidden('addContact');

    try {
        $page->callAction('editAddress', ['street' => 'Calle Nueva']);
    } catch (Throwable) {
        // Rejecting the call is the expected outcome.
    }

    expect(Client::withTrashed()->find($this->client->id)->street)->toBeNull();
});

it('RF-43, RF-59: una cuenta sin vínculo no ve las acciones de edición', function () {
    $unlinked = User::factory()->client()->create();
    $this->actingAs($unlinked);

    Livewire::test(ClientProfilePage::class)
        ->assertActionHidden('editAddress')
        ->assertActionHidden('addContact');
});

it('RF-59: las acciones de edición se ofrecen a la cuenta vinculada', function () {
    Livewire::test(ClientProfilePage::class)
        ->assertActionVisible('editAddress')
        ->assertActionVisible('addContact');
});
