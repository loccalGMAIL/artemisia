<?php

use App\Enums\ClientHistoryField;
use App\Enums\ClientStatus;
use App\Enums\ClientType;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientHistory;
use App\Models\Province;
use App\Models\User;

it('RF-2, RF-23: el tipo de persona y el estado se exponen como enums y el cliente nace activo', function () {
    $client = Client::factory()->create()->refresh();

    expect($client->person_type)->toBe(ClientType::Individual)
        ->and($client->status)->toBe(ClientStatus::Active);

    expect(Client::factory()->company()->create()->person_type)->toBe(ClientType::Company);
});

it('RF-5: el nombre para mostrar sale del nombre y apellido o de la razón social', function () {
    $individual = Client::factory()->create(['first_name' => 'Ana', 'last_name' => 'Pérez']);
    $company = Client::factory()->company()->create(['company_name' => 'Imprenta Sur S.A.']);

    expect($individual->display_name)->toBe('Ana Pérez')
        ->and($company->display_name)->toBe('Imprenta Sur S.A.');
});

it('RF-21: el teléfono de contacto es el del principal y, si no tiene, el de otro contacto', function () {
    $client = Client::factory()->create();
    ClientContact::factory()->for($client)->create(['name' => 'Principal', 'phone' => '1111-1111', 'is_primary' => true]);
    ClientContact::factory()->for($client)->create(['name' => 'Otro', 'phone' => '2222-2222']);

    expect($client->contactPhone())->toBe('1111-1111');

    $client->contacts()->where('is_primary', true)->update(['phone' => null, 'email' => 'principal@example.com']);

    expect($client->refresh()->contactPhone())->toBe('2222-2222');
});

it('RF-22: sin ningún teléfono cargado el cliente no tiene teléfono de contacto', function () {
    $client = Client::factory()->create();

    expect($client->contactPhone())->toBeNull();

    ClientContact::factory()->for($client)->create(['phone' => null, 'email' => 'a@example.com', 'is_primary' => true]);

    expect($client->refresh()->contactPhone())->toBeNull();
});

it('RF-9: el domicilio embebido resuelve su provincia', function () {
    $province = Province::query()->create(['name' => 'Córdoba']);
    $client = Client::factory()->create(['province_id' => $province->id]);

    expect($client->province->is($province))->toBeTrue();
});

it('RF-38: un cliente tiene varias cuentas vinculadas y cada cuenta resuelve su cliente', function () {
    $client = Client::factory()->create();
    $accounts = User::factory()->count(2)->create(['client_id' => $client->id]);

    expect($client->accounts)->toHaveCount(2)
        ->and($accounts->first()->refresh()->client->is($client))->toBeTrue();
});

it('RF-31, RF-34: archivar es un soft delete y el cliente sigue consultable', function () {
    $client = Client::factory()->create();

    $client->delete();

    expect(Client::query()->count())->toBe(0)
        ->and(Client::withTrashed()->count())->toBe(1)
        ->and($client->refresh()->trashed())->toBeTrue();
});

it('RF-37, RNF-7: el asiento del historial resuelve cliente y autor, y su campo es un enum', function () {
    $client = Client::factory()->create();
    $author = User::factory()->create();

    $history = ClientHistory::factory()->for($client)->create([
        'author_id' => $author->id,
        'field' => ClientHistoryField::Status,
        'old_value' => ['status' => 'active'],
        'new_value' => ['status' => 'inactive'],
    ]);

    expect($history->client->is($client))->toBeTrue()
        ->and($history->author->is($author))->toBeTrue()
        ->and($history->field)->toBe(ClientHistoryField::Status)
        ->and($history->new_value)->toBe(['status' => 'inactive'])
        ->and($client->histories)->toHaveCount(1)
        ->and((new ClientHistory)->getUpdatedAtColumn())->toBeNull();
});
