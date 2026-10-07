<?php

use App\Actions\CreateClientAction;
use App\Enums\ClientHistoryField;
use App\Enums\ClientStatus;
use App\Enums\ClientType;
use App\Models\Client;
use App\Models\ClientHistory;
use App\Models\User;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    app()->setLocale('es');
    $this->actor = User::factory()->staff()->create();
});

function individualData(array $overrides = []): array
{
    return array_merge([
        'person_type' => 'individual',
        'first_name' => 'Ana',
        'last_name' => 'Pérez',
        'document' => '12345678',
    ], $overrides);
}

function companyData(array $overrides = []): array
{
    return array_merge([
        'person_type' => 'company',
        'company_name' => 'Imprenta Sur S.A.',
        'document' => '20123456786',
    ], $overrides);
}

function validationErrorsOf(Closure $callback): array
{
    try {
        $callback();
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    return [];
}

it('RF-1, RF-2, RF-3, RF-5, RF-24, RF-35: da de alta una persona física activa y deja el asiento de alta', function () {
    $client = app(CreateClientAction::class)->handle(individualData(), $this->actor);

    $client->refresh();

    expect($client->person_type)->toBe(ClientType::Individual)
        ->and($client->status)->toBe(ClientStatus::Active)
        ->and($client->display_name)->toBe('Ana Pérez')
        ->and($client->document)->toBe('12345678')
        ->and($client->created_by)->toBe($this->actor->id);

    $history = ClientHistory::query()->where('client_id', $client->id)->sole();

    expect($history->field)->toBe(ClientHistoryField::Identification)
        ->and($history->author_id)->toBe($this->actor->id)
        ->and($history->old_value)->toBeNull()
        ->and($history->new_value)->toMatchArray([
            'person_type' => 'individual',
            'first_name' => 'Ana',
            'last_name' => 'Pérez',
            'document' => '12345678',
        ]);
});

it('RF-1, RF-2, RF-4, RF-5, RF-24, RF-35: da de alta una persona jurídica activa', function () {
    $client = app(CreateClientAction::class)->handle(companyData(), $this->actor)->refresh();

    expect($client->person_type)->toBe(ClientType::Company)
        ->and($client->status)->toBe(ClientStatus::Active)
        ->and($client->display_name)->toBe('Imprenta Sur S.A.')
        ->and($client->first_name)->toBeNull()
        ->and(ClientHistory::query()->where('client_id', $client->id)->count())->toBe(1);
});

it('RF-6: normaliza el documento ignorando espacios, puntos y guiones', function (array $data, string $expected) {
    $client = app(CreateClientAction::class)->handle($data, $this->actor);

    expect($client->refresh()->document)->toBe($expected);
})->with([
    'DNI con puntos' => [fn () => individualData(['document' => '12.345.678']), '12345678'],
    'DNI con espacios' => [fn () => individualData(['document' => ' 12 345 678 ']), '12345678'],
    'CUIT con guiones' => [fn () => companyData(['document' => '20-12345678-6']), '20123456786'],
]);

it('RF-6: rechaza un documento repetido, incluso de un cliente archivado, e indica el cliente existente', function (bool $archived) {
    $existing = Client::factory()->create(['first_name' => 'Luis', 'last_name' => 'Gómez', 'document' => '12345678']);

    if ($archived) {
        $existing->delete();
    }

    $errors = validationErrorsOf(fn () => app(CreateClientAction::class)->handle(
        individualData(['document' => '12.345.678']),
        $this->actor,
    ));

    expect($errors)->toHaveKey('document')
        ->and($errors['document'][0])->toContain('12345678')->toContain('Luis Gómez')
        ->and(Client::withTrashed()->count())->toBe(1)
        ->and(ClientHistory::query()->count())->toBe(0);
})->with([
    'cliente activo' => false,
    'cliente archivado' => true,
]);

it('RF-3: la persona física exige nombre, apellido y DNI', function (string $missing) {
    $errors = validationErrorsOf(fn () => app(CreateClientAction::class)->handle(
        individualData([$missing => '']),
        $this->actor,
    ));

    expect($errors)->toHaveKey($missing)
        ->and(Client::query()->count())->toBe(0);
})->with(['first_name', 'last_name', 'document']);

it('RF-4: la persona jurídica exige razón social y CUIT', function (string $missing) {
    $errors = validationErrorsOf(fn () => app(CreateClientAction::class)->handle(
        companyData([$missing => '']),
        $this->actor,
    ));

    expect($errors)->toHaveKey($missing)
        ->and(Client::query()->count())->toBe(0);
})->with(['company_name', 'document']);

it('RF-2: el tipo de persona es obligatorio y solo admite física o jurídica', function (mixed $type) {
    $errors = validationErrorsOf(fn () => app(CreateClientAction::class)->handle(
        individualData(['person_type' => $type]),
        $this->actor,
    ));

    expect($errors)->toHaveKey('person_type');
})->with(['vacío' => '', 'desconocido' => 'organization']);

it('RNF-1: admite nombres, apellidos y razones sociales de hasta 150 caracteres', function () {
    $ok = str_repeat('a', 150);
    $tooLong = str_repeat('a', 151);

    app(CreateClientAction::class)->handle(individualData(['first_name' => $ok, 'last_name' => $ok]), $this->actor);

    foreach (['first_name', 'last_name'] as $field) {
        expect(validationErrorsOf(fn () => app(CreateClientAction::class)->handle(
            individualData([$field => $tooLong, 'document' => '87654321']),
            $this->actor,
        )))->toHaveKey($field);
    }

    expect(validationErrorsOf(fn () => app(CreateClientAction::class)->handle(
        companyData(['company_name' => $tooLong]),
        $this->actor,
    )))->toHaveKey('company_name');
});

it('RNF-2: el DNI admite únicamente 7 u 8 dígitos numéricos', function (string $dni, bool $valid) {
    $errors = validationErrorsOf(fn () => app(CreateClientAction::class)->handle(
        individualData(['document' => $dni]),
        $this->actor,
    ));

    expect(array_key_exists('document', $errors))->toBe(! $valid);
})->with([
    '7 dígitos' => ['1234567', true],
    '8 dígitos' => ['12345678', true],
    '6 dígitos' => ['123456', false],
    '9 dígitos' => ['123456789', false],
    'con letras' => ['1234567a', false],
]);

it('RNF-3: el CUIT admite únicamente 11 dígitos numéricos con dígito verificador válido', function (string $cuit, bool $valid) {
    $errors = validationErrorsOf(fn () => app(CreateClientAction::class)->handle(
        companyData(['document' => $cuit]),
        $this->actor,
    ));

    expect(array_key_exists('document', $errors))->toBe(! $valid);
})->with([
    'válido' => ['20123456786', true],
    'dígito incorrecto' => ['20123456787', false],
    'resto uno, sin dígito posible' => ['20123456000', false],
    '10 dígitos' => ['2012345678', false],
    'un DNI no es un CUIT' => ['12345678', false],
    'con letras' => ['2012345678a', false],
]);
