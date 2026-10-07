<?php

use App\Filament\Client\Pages\ClientProfilePage;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Province;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    app()->setLocale('es');
    Filament::setCurrentPanel('client');
});

function linkedAccount(array $clientAttributes = []): array
{
    $client = Client::factory()->create($clientAttributes);
    $account = User::factory()->client()->create(['client_id' => $client->id]);

    return [$account, $client];
}

it('RF-57: la cuenta vinculada ve la ficha de su cliente en la página inicial del portal', function () {
    $province = Province::query()->create(['name' => 'Córdoba']);
    [$account, $client] = linkedAccount([
        'first_name' => 'Ana', 'last_name' => 'Pérez', 'document' => '12345678',
        'street' => 'Av. Colón', 'street_number' => '1234', 'city' => 'Córdoba', 'province_id' => $province->id,
    ]);
    ClientContact::factory()->for($client)->primary()->create(['name' => 'Marta Ruiz', 'phone' => '11-5555-0001', 'email' => 'marta@example.com']);
    ClientContact::factory()->for($client)->create(['name' => 'Juan Díaz', 'phone' => null, 'email' => 'juan@example.com']);

    $this->actingAs($account)->get('/portal')->assertOk()->assertSeeLivewire(ClientProfilePage::class);

    Livewire::test(ClientProfilePage::class)
        ->assertSee('Ana Pérez')
        ->assertSee('12345678')
        ->assertSee('Persona física')
        ->assertSee('Av. Colón')
        ->assertSee('Córdoba')
        ->assertSee('Marta Ruiz')
        ->assertSee('11-5555-0001')
        ->assertSee('Juan Díaz');
});

it('RF-62: la ficha es siempre la del cliente vinculado, aunque se pida otro desde la URL', function () {
    [$account] = linkedAccount(['first_name' => 'Ana', 'last_name' => 'Pérez']);
    $other = Client::factory()->create(['first_name' => 'Luis', 'last_name' => 'Gómez', 'document' => '87654321']);
    ClientContact::factory()->for($other)->primary()->create(['name' => 'Contacto Ajeno']);

    $this->actingAs($account);

    foreach (["/portal?client={$other->id}", "/portal?client_id={$other->id}", "/portal?record={$other->id}"] as $url) {
        $this->get($url)->assertOk()->assertSee('Ana Pérez')->assertDontSee('Luis Gómez')->assertDontSee('Contacto Ajeno')->assertDontSee('87654321');
    }

    Livewire::withQueryParams(['client' => $other->id])
        ->test(ClientProfilePage::class, ['client' => $other->id, 'client_id' => $other->id])
        ->assertSee('Ana Pérez')
        ->assertDontSee('Luis Gómez');
});

it('RF-62: el portal no tiene ninguna ruta para ver un cliente por su identificador', function () {
    [$account] = linkedAccount();
    $other = Client::factory()->create();

    $this->actingAs($account);

    $this->get("/portal/clients/{$other->id}")->assertNotFound();
    $this->get("/portal/{$other->id}")->assertNotFound();
});

it('RF-62: la sección de clientes del staff rechaza a una cuenta client, incluso sobre su propio cliente', function () {
    [$account, $client] = linkedAccount();

    $this->actingAs($account);

    $this->get('/staff/clients')->assertForbidden();
    $this->get("/staff/clients/{$client->id}")->assertForbidden();
});

it('RF-43: una cuenta sin vínculo ve que su acceso todavía no fue habilitado por la agencia', function () {
    $account = User::factory()->client()->create();
    Client::factory()->create(['first_name' => 'Luis', 'last_name' => 'Gómez']);

    $this->actingAs($account)->get('/portal')->assertOk();

    Livewire::test(ClientProfilePage::class)
        ->assertSee('Su acceso todavía no fue habilitado por la agencia.')
        ->assertDontSee('Luis Gómez');
});

it('RF-58: con el cliente archivado, las cuentas vinculadas no acceden a su ficha', function () {
    [$account, $client] = linkedAccount(['first_name' => 'Ana', 'last_name' => 'Pérez', 'document' => '12345678']);
    ClientContact::factory()->for($client)->primary()->create(['name' => 'Marta Ruiz']);
    $client->delete();

    $this->actingAs($account)->get('/portal')->assertOk();

    Livewire::test(ClientProfilePage::class)
        ->assertSee('La ficha de su cliente no está disponible.')
        ->assertDontSee('Ana Pérez')
        ->assertDontSee('12345678')
        ->assertDontSee('Marta Ruiz');
});

it('RF-58: al restaurar el cliente, la ficha vuelve a estar disponible', function () {
    [$account, $client] = linkedAccount(['first_name' => 'Ana', 'last_name' => 'Pérez']);
    $client->delete();
    $client->restore();

    $this->actingAs($account);

    Livewire::test(ClientProfilePage::class)->assertSee('Ana Pérez');
});

it('RF-16: la ficha es la página inicial del portal de clientes', function () {
    [$account] = linkedAccount();

    expect(Filament::getPanel('client')->getUrl())->toEndWith('/portal');

    $this->actingAs($account)->get(Filament::getPanel('client')->getUrl())->assertOk()->assertSeeLivewire(ClientProfilePage::class);
});
