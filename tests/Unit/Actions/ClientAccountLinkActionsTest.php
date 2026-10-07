<?php

use App\Actions\LinkAccountToClientAction;
use App\Actions\UnlinkAccountFromClientAction;
use App\Enums\ClientHistoryField;
use App\Models\Client;
use App\Models\ClientHistory;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    app()->setLocale('es');
    $this->actor = User::factory()->staff()->create();
    $this->client = Client::factory()->create(['first_name' => 'Ana', 'last_name' => 'Pérez']);
});

function linkErrors(Closure $callback): array
{
    try {
        $callback();
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    return [];
}

it('RF-38, RF-44: vincula una cuenta con rol client a un cliente y lo registra con autor y fecha', function () {
    $account = User::factory()->client()->create(['email' => 'portal@example.com']);

    app(LinkAccountToClientAction::class)->handle($this->client, $account, $this->actor);

    expect($account->refresh()->client_id)->toBe($this->client->id);

    $history = ClientHistory::query()->where('client_id', $this->client->id)->sole();

    expect($history->field)->toBe(ClientHistoryField::AccountLink)
        ->and($history->author_id)->toBe($this->actor->id)
        ->and($history->created_at)->not->toBeNull()
        ->and($history->old_value)->toMatchArray(['account_id' => $account->id, 'client_id' => null])
        ->and($history->new_value)->toMatchArray([
            'account_id' => $account->id,
            'account_email' => 'portal@example.com',
            'client_id' => $this->client->id,
        ]);
});

it('RF-39: se pueden vincular varias cuentas al mismo cliente', function () {
    $accounts = User::factory()->client()->count(3)->create();

    foreach ($accounts as $account) {
        app(LinkAccountToClientAction::class)->handle($this->client, $account, $this->actor);
    }

    expect($this->client->accounts()->count())->toBe(3);
});

it('RF-40: no vincula una cuenta que ya está vinculada a otro cliente y muestra el motivo', function () {
    $other = Client::factory()->create(['first_name' => 'Luis', 'last_name' => 'Gómez']);
    $account = User::factory()->client()->create(['client_id' => $other->id]);

    $errors = linkErrors(fn () => app(LinkAccountToClientAction::class)->handle($this->client, $account, $this->actor));

    expect($errors)->toHaveKey('account')
        ->and($errors['account'][0])->toContain('Luis Gómez')
        ->and($account->refresh()->client_id)->toBe($other->id)
        ->and(ClientHistory::query()->count())->toBe(0);
});

it('RF-38: solo se vinculan cuentas con rol client', function (string $role) {
    $account = User::factory()->{$role}()->create();

    $errors = linkErrors(fn () => app(LinkAccountToClientAction::class)->handle($this->client, $account, $this->actor));

    expect($errors)->toHaveKey('account')
        ->and($account->refresh()->client_id)->toBeNull();
})->with(['admin', 'staff']);

it('RF-44: vincular otra vez la cuenta al mismo cliente no registra nada', function () {
    $account = User::factory()->client()->create(['client_id' => $this->client->id]);

    app(LinkAccountToClientAction::class)->handle($this->client, $account, $this->actor);

    expect(ClientHistory::query()->count())->toBe(0);
});

it('RF-41, RF-44: desvincula una cuenta y lo registra con autor y fecha', function () {
    $account = User::factory()->client()->create(['client_id' => $this->client->id, 'email' => 'portal@example.com']);

    app(UnlinkAccountFromClientAction::class)->handle($account, $this->actor);

    expect($account->refresh()->client_id)->toBeNull();

    $history = ClientHistory::query()->where('client_id', $this->client->id)->sole();

    expect($history->field)->toBe(ClientHistoryField::AccountLink)
        ->and($history->author_id)->toBe($this->actor->id)
        ->and($history->old_value)->toMatchArray(['account_id' => $account->id, 'client_id' => $this->client->id])
        ->and($history->new_value)->toMatchArray(['account_id' => $account->id, 'client_id' => null]);
});

it('RF-41: desvincular una cuenta que no está vinculada no registra nada', function () {
    $account = User::factory()->client()->create();

    app(UnlinkAccountFromClientAction::class)->handle($account, $this->actor);

    expect(ClientHistory::query()->count())->toBe(0);
});

it('RF-41: desvincular conserva la cuenta y no toca a las demás cuentas del cliente', function () {
    $first = User::factory()->client()->create(['client_id' => $this->client->id]);
    $second = User::factory()->client()->create(['client_id' => $this->client->id]);

    app(UnlinkAccountFromClientAction::class)->handle($first, $this->actor);

    expect($first->refresh()->exists)->toBeTrue()
        ->and($second->refresh()->client_id)->toBe($this->client->id);
});
