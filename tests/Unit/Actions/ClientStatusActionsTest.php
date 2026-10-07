<?php

use App\Actions\ActivateClientAction;
use App\Actions\ArchiveClientAction;
use App\Actions\CreateClientAction;
use App\Actions\DeactivateClientAction;
use App\Actions\RestoreClientAction;
use App\Enums\ClientHistoryField;
use App\Enums\ClientStatus;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientHistory;
use App\Models\User;

beforeEach(function () {
    app()->setLocale('es');
    $this->actor = User::factory()->staff()->create();
});

it('RF-23, RF-24: un cliente nuevo queda activo', function () {
    $client = app(CreateClientAction::class)->handle([
        'person_type' => 'individual',
        'first_name' => 'Ana',
        'last_name' => 'Pérez',
        'document' => '12345678',
    ], $this->actor);

    expect($client->refresh()->status)->toBe(ClientStatus::Active);
});

it('RF-25, RF-36: marca un cliente como inactivo y lo reactiva, registrando cada cambio', function () {
    $client = Client::factory()->create();

    app(DeactivateClientAction::class)->handle($client, $this->actor);

    expect($client->refresh()->status)->toBe(ClientStatus::Inactive);

    $deactivation = ClientHistory::query()->where('client_id', $client->id)->sole();

    expect($deactivation->field)->toBe(ClientHistoryField::Status)
        ->and($deactivation->author_id)->toBe($this->actor->id)
        ->and($deactivation->old_value)->toBe(['status' => 'active'])
        ->and($deactivation->new_value)->toBe(['status' => 'inactive']);

    app(ActivateClientAction::class)->handle($client, $this->actor);

    $activation = ClientHistory::query()->where('client_id', $client->id)->latest('id')->first();

    expect($client->refresh()->status)->toBe(ClientStatus::Active)
        ->and($activation->old_value)->toBe(['status' => 'inactive'])
        ->and($activation->new_value)->toBe(['status' => 'active']);
});

it('RF-36: activar un cliente ya activo, o desactivar uno ya inactivo, no registra nada', function () {
    $active = Client::factory()->create();
    $inactive = Client::factory()->inactive()->create();

    app(ActivateClientAction::class)->handle($active, $this->actor);
    app(DeactivateClientAction::class)->handle($inactive, $this->actor);

    expect(ClientHistory::query()->count())->toBe(0);
});

it('RF-26: un cliente inactivo no se ofrece para presupuestos nuevos', function () {
    $active = Client::factory()->create();
    $inactive = Client::factory()->inactive()->create();

    expect(Client::availableForBudgets()->pluck('id')->all())->toBe([$active->id])
        ->and(Client::availableForBudgets()->whereKey($inactive->id)->exists())->toBeFalse();
});

it('RF-27: un cliente inactivo conserva ficha, contactos e historial consultables', function () {
    $client = Client::factory()->create();
    ClientContact::factory()->for($client)->primary()->create();

    app(DeactivateClientAction::class)->handle($client, $this->actor);

    $found = Client::query()->findOrFail($client->id);

    expect($found->contacts)->toHaveCount(1)
        ->and($found->histories)->toHaveCount(1);
});

it('RF-28, RF-29, RF-36: archiva un cliente, lo marca inactivo y registra el asiento', function () {
    $client = Client::factory()->create();

    app(ArchiveClientAction::class)->handle($client, $this->actor);

    expect($client->refresh()->trashed())->toBeTrue()
        ->and($client->status)->toBe(ClientStatus::Inactive);

    $history = ClientHistory::query()->where('client_id', $client->id)->sole();

    expect($history->field)->toBe(ClientHistoryField::Archived)
        ->and($history->author_id)->toBe($this->actor->id)
        ->and($history->old_value)->toBe(['archived' => false, 'status' => 'active'])
        ->and($history->new_value)->toBe(['archived' => true, 'status' => 'inactive']);
});

it('RF-30: un cliente archivado sale del listado por defecto y no se ofrece para presupuestos', function () {
    $visible = Client::factory()->create();
    $archived = Client::factory()->create();

    app(ArchiveClientAction::class)->handle($archived, $this->actor);

    expect(Client::query()->pluck('id')->all())->toBe([$visible->id])
        ->and(Client::availableForBudgets()->pluck('id')->all())->toBe([$visible->id])
        ->and(Client::withTrashed()->count())->toBe(2);
});

it('RF-31, RF-34: archivar conserva el cliente, sus contactos y su historial, sin eliminar nada', function () {
    $client = Client::factory()->create();
    ClientContact::factory()->for($client)->primary()->create();
    ClientHistory::factory()->for($client)->create();

    app(ArchiveClientAction::class)->handle($client, $this->actor);

    $archived = Client::onlyTrashed()->findOrFail($client->id);

    expect($archived->contacts)->toHaveCount(1)
        ->and($archived->histories)->toHaveCount(2)
        ->and(Client::withTrashed()->whereKey($client->id)->exists())->toBeTrue();
});

it('RF-32, RF-33, RF-36: restaura un cliente archivado y queda inactivo, sin reactivarse solo', function () {
    $client = Client::factory()->create();
    app(ArchiveClientAction::class)->handle($client, $this->actor);

    app(RestoreClientAction::class)->handle($client->refresh(), $this->actor);

    expect($client->refresh()->trashed())->toBeFalse()
        ->and($client->status)->toBe(ClientStatus::Inactive)
        ->and(Client::query()->whereKey($client->id)->exists())->toBeTrue()
        ->and(Client::availableForBudgets()->whereKey($client->id)->exists())->toBeFalse();

    $history = ClientHistory::query()->where('client_id', $client->id)->latest('id')->first();

    expect($history->field)->toBe(ClientHistoryField::Archived)
        ->and($history->author_id)->toBe($this->actor->id)
        ->and($history->old_value)->toBe(['archived' => true, 'status' => 'inactive'])
        ->and($history->new_value)->toBe(['archived' => false, 'status' => 'inactive']);

    app(ActivateClientAction::class)->handle($client, $this->actor);

    expect(Client::availableForBudgets()->whereKey($client->id)->exists())->toBeTrue();
});

it('RF-34: el sistema no ofrece ninguna acción que elimine un cliente definitivamente', function () {
    $actions = [
        ActivateClientAction::class, DeactivateClientAction::class,
        ArchiveClientAction::class, RestoreClientAction::class,
    ];

    foreach ($actions as $action) {
        expect(method_exists($action, 'forceDelete'))->toBeFalse();
    }

    $client = Client::factory()->create();
    app(ArchiveClientAction::class)->handle($client, $this->actor);
    app(RestoreClientAction::class)->handle($client->refresh(), $this->actor);
    app(DeactivateClientAction::class)->handle($client->refresh(), $this->actor);

    expect(Client::withTrashed()->count())->toBe(1);
});
