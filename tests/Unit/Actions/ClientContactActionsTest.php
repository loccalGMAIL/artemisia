<?php

use App\Actions\AddClientContactAction;
use App\Actions\RemoveClientContactAction;
use App\Actions\SetPrimaryContactAction;
use App\Actions\UpdateClientContactAction;
use App\Enums\ClientHistoryField;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientHistory;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    app()->setLocale('es');
    $this->actor = User::factory()->staff()->create();
    $this->client = Client::factory()->create();
});

function contactData(array $overrides = []): array
{
    return array_merge([
        'name' => 'Marta Ruiz',
        'role' => 'Compras',
        'phone' => '11-5555-0001',
        'email' => 'marta@example.com',
    ], $overrides);
}

function addContact(Client $client, User $actor, array $overrides = []): ClientContact
{
    return app(AddClientContactAction::class)->handle($client, contactData($overrides), $actor);
}

function primaryCount(Client $client): int
{
    return $client->contacts()->where('is_primary', true)->count();
}

function contactErrors(Closure $callback): array
{
    try {
        $callback();
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    return [];
}

it('RF-14: un cliente puede existir sin ningún contacto', function () {
    expect($this->client->contacts()->count())->toBe(0)
        ->and($this->client->contactPhone())->toBeNull();
});

it('RF-13, RF-16, RF-36: agrega un contacto, el primero queda como principal y se registra el asiento', function () {
    $contact = addContact($this->client, $this->actor);

    expect($contact->refresh()->is_primary)->toBeTrue()
        ->and($contact->name)->toBe('Marta Ruiz')
        ->and($contact->role)->toBe('Compras')
        ->and($contact->phone)->toBe('11-5555-0001')
        ->and($contact->email)->toBe('marta@example.com');

    $history = ClientHistory::query()->where('client_id', $this->client->id)->sole();

    expect($history->field)->toBe(ClientHistoryField::Contacts)
        ->and($history->author_id)->toBe($this->actor->id)
        ->and($history->old_value)->toBeNull()
        ->and($history->new_value)->toMatchArray(['id' => $contact->id, 'name' => 'Marta Ruiz', 'is_primary' => true]);
});

it('RF-13, RF-17: los contactos siguientes no son principales y siempre hay exactamente un principal', function () {
    $first = addContact($this->client, $this->actor);
    $second = addContact($this->client, $this->actor, ['name' => 'Juan Díaz', 'email' => 'juan@example.com']);

    expect($first->refresh()->is_primary)->toBeTrue()
        ->and($second->refresh()->is_primary)->toBeFalse()
        ->and(primaryCount($this->client))->toBe(1);
});

it('RF-17: la base impide que un cliente tenga dos contactos principales', function () {
    ClientContact::factory()->for($this->client)->primary()->create();

    expect(fn () => ClientContact::factory()->for($this->client)->primary()->create())
        ->toThrow(QueryException::class);

    ClientContact::factory()->for(Client::factory()->create())->primary()->create();

    expect(ClientContact::query()->where('is_primary', true)->count())->toBe(2);
});

it('RF-15: cada contacto exige al menos un teléfono o un email', function (?string $phone, ?string $email, bool $valid) {
    $errors = contactErrors(fn () => addContact($this->client, $this->actor, ['phone' => $phone, 'email' => $email]));

    expect(array_key_exists('phone', $errors))->toBe(! $valid)
        ->and($this->client->contacts()->count())->toBe($valid ? 1 : 0);
})->with([
    'solo teléfono' => ['11-5555-0001', null, true],
    'solo email' => [null, 'marta@example.com', true],
    'ambos' => ['11-5555-0001', 'marta@example.com', true],
    'ninguno' => [null, null, false],
    'vacíos' => ['', '', false],
]);

it('RF-13: valida nombre obligatorio, email con formato y largos máximos', function () {
    expect(contactErrors(fn () => addContact($this->client, $this->actor, ['name' => ''])))->toHaveKey('name')
        ->and(contactErrors(fn () => addContact($this->client, $this->actor, ['email' => 'no-es-un-email'])))->toHaveKey('email')
        ->and(contactErrors(fn () => addContact($this->client, $this->actor, ['name' => str_repeat('a', 151)])))->toHaveKey('name')
        ->and(contactErrors(fn () => addContact($this->client, $this->actor, ['role' => str_repeat('a', 101)])))->toHaveKey('role')
        ->and(contactErrors(fn () => addContact($this->client, $this->actor, ['phone' => str_repeat('1', 31)])))->toHaveKey('phone');
});

it('RNF-4: admite hasta 10 contactos por cliente', function () {
    foreach (range(1, 10) as $number) {
        addContact($this->client, $this->actor, ['name' => "Contacto {$number}", 'email' => "c{$number}@example.com"]);
    }

    $errors = contactErrors(fn () => addContact($this->client, $this->actor, ['name' => 'Contacto 11']));

    expect($errors)->toHaveKey('contacts')
        ->and($this->client->contacts()->count())->toBe(10)
        ->and(primaryCount($this->client))->toBe(1);
});

it('RF-18, RF-36: cambia cuál es el contacto principal y registra el asiento', function () {
    $first = addContact($this->client, $this->actor);
    $second = addContact($this->client, $this->actor, ['name' => 'Juan Díaz', 'email' => 'juan@example.com']);

    app(SetPrimaryContactAction::class)->handle($this->client, $second, $this->actor);

    expect($first->refresh()->is_primary)->toBeFalse()
        ->and($second->refresh()->is_primary)->toBeTrue()
        ->and(primaryCount($this->client))->toBe(1);

    $history = ClientHistory::query()->where('client_id', $this->client->id)->latest('id')->first();

    expect($history->field)->toBe(ClientHistoryField::Contacts)
        ->and($history->author_id)->toBe($this->actor->id)
        ->and($history->old_value)->toMatchArray(['primary_contact_id' => $first->id])
        ->and($history->new_value)->toMatchArray(['primary_contact_id' => $second->id]);
});

it('RF-18: no permite marcar como principal un contacto de otro cliente', function () {
    addContact($this->client, $this->actor);
    $foreign = ClientContact::factory()->for(Client::factory()->create())->primary()->create();

    $errors = contactErrors(fn () => app(SetPrimaryContactAction::class)->handle($this->client, $foreign, $this->actor));

    expect($errors)->toHaveKey('contact')
        ->and(primaryCount($this->client))->toBe(1);
});

it('RF-19, RF-36: modifica un contacto y registra valor anterior y nuevo', function () {
    $contact = addContact($this->client, $this->actor);

    app(UpdateClientContactAction::class)->handle($contact, [
        'name' => 'Marta R. Ruiz',
        'role' => 'Gerencia',
        'phone' => null,
        'email' => 'marta.ruiz@example.com',
    ], $this->actor);

    expect($contact->refresh()->name)->toBe('Marta R. Ruiz')
        ->and($contact->phone)->toBeNull()
        ->and($contact->is_primary)->toBeTrue();

    $history = ClientHistory::query()->where('client_id', $this->client->id)->latest('id')->first();

    expect($history->old_value)->toMatchArray(['name' => 'Marta Ruiz', 'phone' => '11-5555-0001'])
        ->and($history->new_value)->toMatchArray(['name' => 'Marta R. Ruiz', 'phone' => null, 'email' => 'marta.ruiz@example.com']);
});

it('RF-15, RF-19: al modificar un contacto sigue exigiendo teléfono o email', function () {
    $contact = addContact($this->client, $this->actor);

    $errors = contactErrors(fn () => app(UpdateClientContactAction::class)->handle(
        $contact,
        ['name' => 'Marta Ruiz', 'phone' => null, 'email' => null],
        $this->actor,
    ));

    expect($errors)->toHaveKey('phone')
        ->and($contact->refresh()->phone)->toBe('11-5555-0001');
});

it('RF-19, RF-20: al quitar el contacto principal otro pasa a ser principal', function () {
    $first = addContact($this->client, $this->actor);
    $second = addContact($this->client, $this->actor, ['name' => 'Juan Díaz', 'email' => 'juan@example.com']);

    app(RemoveClientContactAction::class)->handle($first, $this->actor);

    expect(ClientContact::query()->whereKey($first->id)->exists())->toBeFalse()
        ->and($second->refresh()->is_primary)->toBeTrue()
        ->and(primaryCount($this->client))->toBe(1);
});

it('RF-19: quitar un contacto que no es principal no cambia el principal', function () {
    $first = addContact($this->client, $this->actor);
    $second = addContact($this->client, $this->actor, ['name' => 'Juan Díaz', 'email' => 'juan@example.com']);

    app(RemoveClientContactAction::class)->handle($second, $this->actor);

    expect($first->refresh()->is_primary)->toBeTrue()
        ->and($this->client->contacts()->count())->toBe(1);
});

it('RF-14, RF-19: se puede quitar el último contacto y el cliente queda sin ninguno', function () {
    $only = addContact($this->client, $this->actor);

    app(RemoveClientContactAction::class)->handle($only, $this->actor);

    expect($this->client->contacts()->count())->toBe(0);
});

it('RF-36: quitar un contacto conserva su snapshot en el historial, con autor y fecha', function () {
    $first = addContact($this->client, $this->actor);
    addContact($this->client, $this->actor, ['name' => 'Juan Díaz', 'email' => 'juan@example.com']);

    app(RemoveClientContactAction::class)->handle($first, $this->actor);

    $history = ClientHistory::query()->where('client_id', $this->client->id)->latest('id')->first();

    expect($history->field)->toBe(ClientHistoryField::Contacts)
        ->and($history->author_id)->toBe($this->actor->id)
        ->and($history->created_at)->not->toBeNull()
        ->and($history->old_value)->toMatchArray(['id' => $first->id, 'name' => 'Marta Ruiz', 'is_primary' => true])
        ->and($history->new_value)->toMatchArray(['removed' => true]);
});

it('RF-21: el teléfono de contacto es el del principal o, si no tiene, el de otro contacto', function () {
    $first = addContact($this->client, $this->actor, ['phone' => null]);
    addContact($this->client, $this->actor, ['name' => 'Juan Díaz', 'phone' => '11-5555-0002', 'email' => 'juan@example.com']);

    expect($first->refresh()->is_primary)->toBeTrue()
        ->and($this->client->contactPhone())->toBe('11-5555-0002');

    app(UpdateClientContactAction::class)->handle($first, ['name' => 'Marta Ruiz', 'phone' => '11-5555-0001', 'email' => null], $this->actor);

    expect($this->client->contactPhone())->toBe('11-5555-0001');
});

it('RF-22: si ningún contacto tiene teléfono el cliente no tiene teléfono de contacto', function () {
    addContact($this->client, $this->actor, ['phone' => null]);
    addContact($this->client, $this->actor, ['name' => 'Juan Díaz', 'phone' => null, 'email' => 'juan@example.com']);

    expect($this->client->contactPhone())->toBeNull();
});
