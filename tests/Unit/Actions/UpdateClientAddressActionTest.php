<?php

use App\Actions\CreateClientAction;
use App\Actions\UpdateClientAddressAction;
use App\Enums\ClientHistoryField;
use App\Models\Client;
use App\Models\ClientHistory;
use App\Models\Province;
use App\Models\User;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    app()->setLocale('es');
    $this->actor = User::factory()->staff()->create();
    $this->province = Province::query()->create(['name' => 'Córdoba']);
});

function addressData(array $overrides = []): array
{
    return array_merge([
        'street' => 'Av. Colón',
        'street_number' => '1234',
        'city' => 'Córdoba',
        'province_id' => null,
        'postal_code' => 'X5000',
    ], $overrides);
}

it('RF-10: el alta de un cliente no exige domicilio', function () {
    $client = app(CreateClientAction::class)->handle([
        'person_type' => 'individual',
        'first_name' => 'Ana',
        'last_name' => 'Pérez',
        'document' => '12345678',
    ], $this->actor)->refresh();

    expect($client->street)->toBeNull()
        ->and($client->street_number)->toBeNull()
        ->and($client->city)->toBeNull()
        ->and($client->province_id)->toBeNull()
        ->and($client->postal_code)->toBeNull();
});

it('RF-9, RF-36: carga el domicilio con calle, número, localidad, provincia y código postal y registra el asiento', function () {
    $client = Client::factory()->create();

    app(UpdateClientAddressAction::class)->handle(
        $client,
        addressData(['province_id' => $this->province->id]),
        $this->actor,
    );

    expect($client->refresh()->street)->toBe('Av. Colón')
        ->and($client->street_number)->toBe('1234')
        ->and($client->city)->toBe('Córdoba')
        ->and($client->province_id)->toBe($this->province->id)
        ->and($client->postal_code)->toBe('X5000');

    $history = ClientHistory::query()->where('client_id', $client->id)->sole();

    expect($history->field)->toBe(ClientHistoryField::Address)
        ->and($history->author_id)->toBe($this->actor->id)
        ->and($history->old_value)->toMatchArray(['street' => null, 'city' => null, 'province_id' => null])
        ->and($history->new_value)->toMatchArray([
            'street' => 'Av. Colón',
            'street_number' => '1234',
            'city' => 'Córdoba',
            'province_id' => $this->province->id,
            'postal_code' => 'X5000',
        ]);
});

it('RF-11, RF-12, RF-36: modificar el domicilio lo reemplaza y registra valor anterior y nuevo', function () {
    $client = Client::factory()->create(['street' => 'Av. Colón', 'street_number' => '1234', 'city' => 'Córdoba']);

    app(UpdateClientAddressAction::class)->handle(
        $client,
        addressData(['street' => 'Bv. San Juan', 'street_number' => '50', 'city' => 'Villa María']),
        $this->actor,
    );

    expect($client->refresh()->street)->toBe('Bv. San Juan')
        ->and($client->city)->toBe('Villa María');

    $history = ClientHistory::query()->where('client_id', $client->id)->sole();

    expect($history->old_value)->toMatchArray(['street' => 'Av. Colón', 'street_number' => '1234', 'city' => 'Córdoba'])
        ->and($history->new_value)->toMatchArray(['street' => 'Bv. San Juan', 'street_number' => '50', 'city' => 'Villa María']);
});

it('RF-9, RF-10: admite un domicilio parcial y permite vaciarlo', function () {
    $client = Client::factory()->create(['street' => 'Av. Colón', 'city' => 'Córdoba']);

    app(UpdateClientAddressAction::class)->handle($client, ['city' => 'Rosario'], $this->actor);

    expect($client->refresh()->city)->toBe('Rosario')
        ->and($client->street)->toBeNull();

    app(UpdateClientAddressAction::class)->handle($client, [], $this->actor);

    expect($client->refresh()->city)->toBeNull();
});

it('RF-36: si el domicilio no cambia no se registra ningún asiento', function () {
    $client = Client::factory()->create(['street' => 'Av. Colón', 'street_number' => '1234']);

    app(UpdateClientAddressAction::class)->handle(
        $client,
        ['street' => 'Av. Colón', 'street_number' => '1234'],
        $this->actor,
    );

    expect(ClientHistory::query()->count())->toBe(0);
});

it('RF-9: la provincia debe ser una de las cargadas y los campos respetan su largo', function () {
    $client = Client::factory()->create();

    $errors = function (array $data) use ($client): array {
        try {
            app(UpdateClientAddressAction::class)->handle($client, $data, $this->actor);
        } catch (ValidationException $exception) {
            return $exception->errors();
        }

        return [];
    };

    expect($errors(addressData(['province_id' => 9999])))->toHaveKey('province_id')
        ->and($errors(addressData(['street' => str_repeat('a', 151)])))->toHaveKey('street')
        ->and($errors(addressData(['street_number' => str_repeat('1', 21)])))->toHaveKey('street_number')
        ->and($errors(addressData(['city' => str_repeat('a', 101)])))->toHaveKey('city')
        ->and($errors(addressData(['postal_code' => str_repeat('1', 16)])))->toHaveKey('postal_code')
        ->and(ClientHistory::query()->count())->toBe(0);
});
