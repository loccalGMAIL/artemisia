<?php

use App\Models\Client;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    app()->setLocale('es');
    $this->client = Client::factory()->create();
});

const STAFF_ABILITIES_ON_RECORD = ['view', 'update', 'archive', 'restore', 'linkAccount', 'unlinkAccount'];

it('RF-1, RF-7, RF-25, RF-28, RF-32, RF-38, RF-41, RF-45, RF-56: admin y staff tienen exactamente los mismos permisos sobre clientes', function (string $role) {
    $user = User::factory()->{$role}()->create();

    foreach (STAFF_ABILITIES_ON_RECORD as $ability) {
        expect(Gate::forUser($user)->allows($ability, $this->client))->toBeTrue("{$role} debería poder {$ability}");
    }

    foreach (['viewAny', 'create', 'export'] as $ability) {
        expect(Gate::forUser($user)->allows($ability, Client::class))->toBeTrue("{$role} debería poder {$ability}");
    }
})->with(['admin', 'staff']);

it('RF-60, RF-62: una cuenta client nunca pasa por la policy de staff, ni siquiera sobre su propio cliente', function () {
    $account = User::factory()->client()->create(['client_id' => $this->client->id]);

    foreach (STAFF_ABILITIES_ON_RECORD as $ability) {
        $inspection = Gate::forUser($account)->inspect($ability, $this->client);

        expect($inspection->denied())->toBeTrue("client no debería poder {$ability}")
            ->and($inspection->message())->toContain('permiso');
    }

    foreach (['viewAny', 'create', 'export'] as $ability) {
        expect(Gate::forUser($account)->denies($ability, Client::class))->toBeTrue("client no debería poder {$ability}");
    }
});

it('RF-34: nadie puede eliminar un cliente, ni siquiera un admin', function (string $ability) {
    $admin = User::factory()->admin()->create();

    expect(Gate::forUser($admin)->denies($ability, $this->client))->toBeTrue();
})->with(['delete', 'forceDelete']);

it('RF-57: una cuenta vinculada puede ver la ficha de su propio cliente', function () {
    $account = User::factory()->client()->create(['client_id' => $this->client->id]);

    expect(Gate::forUser($account)->allows('portal.view', $this->client))->toBeTrue();
});

it('RF-59: una cuenta vinculada puede modificar el domicilio y los contactos de su cliente', function (string $ability) {
    $account = User::factory()->client()->create(['client_id' => $this->client->id]);

    expect(Gate::forUser($account)->allows($ability, $this->client))->toBeTrue();
})->with(['portal.updateAddress', 'portal.updateContacts']);

it('RF-62: una cuenta vinculada no accede a otro cliente y se le indica permiso insuficiente', function (string $ability) {
    $account = User::factory()->client()->create(['client_id' => $this->client->id]);
    $other = Client::factory()->create();

    $inspection = Gate::forUser($account)->inspect($ability, $other);

    expect($inspection->denied())->toBeTrue()
        ->and($inspection->message())->toContain('permiso');
})->with(['portal.view', 'portal.updateAddress', 'portal.updateContacts']);

it('RF-43: una cuenta sin vínculo recibe el aviso de que su acceso todavía no fue habilitado', function (string $ability) {
    $account = User::factory()->client()->create();

    $inspection = Gate::forUser($account)->inspect($ability, $account->client);

    expect($inspection->denied())->toBeTrue()
        ->and($inspection->message())->toContain('todavía no fue habilitado');
})->with(['portal.view', 'portal.updateAddress', 'portal.updateContacts']);

it('RF-58: un cliente archivado bloquea el acceso de sus cuentas vinculadas al portal', function (string $ability) {
    $account = User::factory()->client()->create(['client_id' => $this->client->id]);

    $this->client->delete();

    expect(Gate::forUser($account)->denies($ability, $this->client))->toBeTrue();
})->with(['portal.view', 'portal.updateAddress', 'portal.updateContacts']);

it('RF-60: las habilidades del portal no incluyen identificación, estado ni archivado', function () {
    foreach (['portal.updateIdentification', 'portal.updateStatus', 'portal.archive'] as $ability) {
        expect(Gate::has($ability))->toBeFalse();
    }

    expect(Gate::has('portal.view'))->toBeTrue();
});

it('RF-57: las cuentas de staff no usan las habilidades del portal', function (string $role) {
    $user = User::factory()->{$role}()->create();

    expect(Gate::forUser($user)->denies('portal.view', $this->client))->toBeTrue();
})->with(['admin', 'staff']);
