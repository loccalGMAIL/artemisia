<?php

use App\Actions\UpdateClientIdentificationAction;
use App\Enums\ClientHistoryField;
use App\Enums\ClientType;
use App\Models\Client;
use App\Models\ClientHistory;
use App\Models\User;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    app()->setLocale('es');
    $this->actor = User::factory()->staff()->create();
});

function errorsWhenUpdating(Client $client, array $data, User $actor): array
{
    try {
        app(UpdateClientIdentificationAction::class)->handle($client, $data, $actor);
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    return [];
}

it('RF-7, RF-36: modifica nombre, apellido y documento y registra valor anterior, nuevo, autor y fecha', function () {
    $client = Client::factory()->create(['first_name' => 'Ana', 'last_name' => 'Pérez', 'document' => '12345678']);

    $result = app(UpdateClientIdentificationAction::class)->handle($client, [
        'person_type' => 'individual',
        'first_name' => 'Anabel',
        'last_name' => 'Pérez Gómez',
        'document' => '87.654.321',
    ], $this->actor);

    expect($result->refresh()->first_name)->toBe('Anabel')
        ->and($result->last_name)->toBe('Pérez Gómez')
        ->and($result->document)->toBe('87654321');

    $history = ClientHistory::query()->where('client_id', $client->id)->sole();

    expect($history->field)->toBe(ClientHistoryField::Identification)
        ->and($history->author_id)->toBe($this->actor->id)
        ->and($history->created_at)->not->toBeNull()
        ->and($history->old_value)->toMatchArray(['first_name' => 'Ana', 'last_name' => 'Pérez', 'document' => '12345678'])
        ->and($history->new_value)->toMatchArray(['first_name' => 'Anabel', 'last_name' => 'Pérez Gómez', 'document' => '87654321']);
});

it('RF-7: modifica la razón social y el CUIT de una persona jurídica', function () {
    $client = Client::factory()->company()->create(['company_name' => 'Imprenta Sur', 'document' => '30712345671']);

    app(UpdateClientIdentificationAction::class)->handle($client, [
        'person_type' => 'company',
        'company_name' => 'Imprenta Sur S.R.L.',
        'document' => '20-12345678-6',
    ], $this->actor);

    expect($client->refresh()->company_name)->toBe('Imprenta Sur S.R.L.')
        ->and($client->document)->toBe('20123456786');
});

it('RF-8: impide cambiar el tipo de persona e indica que debe darse de alta un cliente nuevo', function (string $newType, array $data) {
    $client = Client::factory()->create(['document' => '12345678']);

    $errors = errorsWhenUpdating($client, [...$data, 'person_type' => $newType], $this->actor);

    expect($errors)->toHaveKey('person_type')
        ->and($errors['person_type'][0])->toContain('cliente nuevo')
        ->and($client->refresh()->person_type)->toBe(ClientType::Individual)
        ->and($client->document)->toBe('12345678')
        ->and(ClientHistory::query()->count())->toBe(0);
})->with([
    'a persona jurídica' => ['company', ['company_name' => 'Nueva S.A.', 'document' => '20123456786']],
]);

it('RF-7: aceptar el mismo tipo de persona no es un cambio', function () {
    $client = Client::factory()->create(['first_name' => 'Ana', 'last_name' => 'Pérez']);

    app(UpdateClientIdentificationAction::class)->handle($client, [
        'person_type' => ClientType::Individual->value,
        'first_name' => 'Ana María',
        'last_name' => 'Pérez',
        'document' => $client->document,
    ], $this->actor);

    expect($client->refresh()->first_name)->toBe('Ana María');
});

it('RF-36: si no cambia nada no se registra ningún asiento', function () {
    $client = Client::factory()->create(['first_name' => 'Ana', 'last_name' => 'Pérez', 'document' => '12345678']);

    app(UpdateClientIdentificationAction::class)->handle($client, [
        'person_type' => 'individual',
        'first_name' => 'Ana',
        'last_name' => 'Pérez',
        'document' => '12.345.678',
    ], $this->actor);

    expect(ClientHistory::query()->count())->toBe(0);
});

it('RF-6: rechaza un documento de otro cliente, activo o archivado, y acepta el propio', function (bool $archived) {
    $other = Client::factory()->create(['first_name' => 'Luis', 'last_name' => 'Gómez', 'document' => '11111111']);
    $client = Client::factory()->create(['first_name' => 'Ana', 'last_name' => 'Pérez', 'document' => '22222222']);

    if ($archived) {
        $other->delete();
    }

    $errors = errorsWhenUpdating($client, [
        'person_type' => 'individual',
        'first_name' => 'Ana',
        'last_name' => 'Pérez',
        'document' => '11.111.111',
    ], $this->actor);

    expect($errors)->toHaveKey('document')
        ->and($errors['document'][0])->toContain('Luis Gómez')
        ->and($client->refresh()->document)->toBe('22222222');

    expect(errorsWhenUpdating($client, [
        'person_type' => 'individual',
        'first_name' => 'Ana',
        'last_name' => 'Pérez',
        'document' => '22.222.222',
    ], $this->actor))->toBe([]);
})->with([
    'cliente activo' => false,
    'cliente archivado' => true,
]);

it('RNF-2: el DNI editado admite únicamente 7 u 8 dígitos numéricos', function (string $dni, bool $valid) {
    $client = Client::factory()->create();

    $errors = errorsWhenUpdating($client, [
        'person_type' => 'individual',
        'first_name' => 'Ana',
        'last_name' => 'Pérez',
        'document' => $dni,
    ], $this->actor);

    expect(array_key_exists('document', $errors))->toBe(! $valid);
})->with([
    '7 dígitos' => ['1234567', true],
    '8 dígitos' => ['12345678', true],
    '6 dígitos' => ['123456', false],
    '9 dígitos' => ['123456789', false],
    'con letras' => ['1234567a', false],
]);

it('RNF-3: el CUIT editado admite únicamente 11 dígitos con dígito verificador válido', function (string $cuit, bool $valid) {
    $client = Client::factory()->company()->create();

    $errors = errorsWhenUpdating($client, [
        'person_type' => 'company',
        'company_name' => 'Imprenta Sur',
        'document' => $cuit,
    ], $this->actor);

    expect(array_key_exists('document', $errors))->toBe(! $valid);
})->with([
    'válido' => ['20123456786', true],
    'dígito incorrecto' => ['20123456787', false],
    'un DNI no es un CUIT' => ['12345678', false],
]);

it('RNF-1, RF-3, RF-4: exige los campos de su tipo y limita su largo a 150 caracteres', function () {
    $individual = Client::factory()->create();
    $company = Client::factory()->company()->create();
    $tooLong = str_repeat('a', 151);

    expect(errorsWhenUpdating($individual, [
        'person_type' => 'individual', 'first_name' => '', 'last_name' => $tooLong, 'document' => $individual->document,
    ], $this->actor))->toHaveKeys(['first_name', 'last_name']);

    expect(errorsWhenUpdating($company, [
        'person_type' => 'company', 'company_name' => $tooLong, 'document' => $company->document,
    ], $this->actor))->toHaveKey('company_name');
});
