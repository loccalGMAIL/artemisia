<?php

use App\Actions\UnlinkAccountFromClientAction;
use App\Models\Client;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    app()->setLocale('es');
    Filament::setCurrentPanel('client');
    $this->staff = User::factory()->staff()->create();
});

it('RF-42: al desvincular una cuenta con sesión abierta en el portal, se corta en su siguiente solicitud', function () {
    $client = Client::factory()->create();
    $account = User::factory()->client()->create(['client_id' => $client->id]);

    $this->actingAs($account)->get('/portal')->assertOk();

    // Staff unlinks the account from another request; the open session keeps a stale instance.
    app(UnlinkAccountFromClientAction::class)->handle(User::query()->findOrFail($account->id), $this->staff);

    $this->get('/portal')->assertRedirect('/portal/login');

    $this->assertGuest();
});

it('RF-42: también se corta si la cuenta pasa a estar vinculada a otro cliente', function () {
    $first = Client::factory()->create();
    $second = Client::factory()->create();
    $account = User::factory()->client()->create(['client_id' => $first->id]);

    $this->actingAs($account)->get('/portal')->assertOk();

    User::query()->whereKey($account->id)->update(['client_id' => $second->id]);

    $this->get('/portal')->assertRedirect('/portal/login');
});

it('RF-42: una cuenta que sigue vinculada no se corta', function () {
    $client = Client::factory()->create();
    $account = User::factory()->client()->create(['client_id' => $client->id]);

    $this->actingAs($account)->get('/portal')->assertOk();
    $this->get('/portal')->assertOk();

    $this->assertAuthenticatedAs($account);
});

it('RF-42: una cuenta sin vínculo desde el ingreso no se corta por esta regla', function () {
    $account = User::factory()->client()->create();

    $this->actingAs($account)->get('/portal')->assertOk();
    $this->get('/portal')->assertOk();
});
