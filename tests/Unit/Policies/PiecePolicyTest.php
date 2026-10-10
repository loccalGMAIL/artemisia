<?php

use App\Models\Piece;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    app()->setLocale('es');
    $this->piece = Piece::factory()->create();
});

const STAFF_ABILITIES_ON_PIECE = ['view', 'update', 'discard'];

it('RF-4, RF-11, RF-15, RF-28, RF-36, RF-39: admin y staff tienen exactamente los mismos permisos sobre las piezas', function (string $role) {
    $user = User::factory()->{$role}()->create();

    foreach (STAFF_ABILITIES_ON_PIECE as $ability) {
        expect(Gate::forUser($user)->allows($ability, $this->piece))->toBeTrue("{$role} debería poder {$ability}");
    }

    foreach (['viewAny', 'create'] as $ability) {
        expect(Gate::forUser($user)->allows($ability, Piece::class))->toBeTrue("{$role} debería poder {$ability}");
    }
})->with(['admin', 'staff']);

it('RF-4: una cuenta client nunca pasa por la policy de piezas del staff, ni siquiera sobre las piezas de su cliente', function () {
    $account = User::factory()->client()->create(['client_id' => $this->piece->budget->client_id]);

    foreach (STAFF_ABILITIES_ON_PIECE as $ability) {
        $inspection = Gate::forUser($account)->inspect($ability, $this->piece);

        expect($inspection->denied())->toBeTrue("client no debería poder {$ability}")
            ->and($inspection->message())->toContain('permiso');
    }

    foreach (['viewAny', 'create'] as $ability) {
        expect(Gate::forUser($account)->denies($ability, Piece::class))->toBeTrue("client no debería poder {$ability}");
    }
});

it('RF-40: nadie puede eliminar físicamente una pieza ni restaurarla, ni siquiera un admin', function (string $ability) {
    $admin = User::factory()->admin()->create();

    expect(Gate::getPolicyFor(Piece::class))->not->toBeNull()
        ->and(Gate::forUser($admin)->denies($ability, $this->piece))->toBeTrue();
})->with(['delete', 'forceDelete', 'restore']);

it('RF-4: una cuenta sin rol no accede a las piezas', function () {
    $user = User::factory()->create();

    expect(Gate::getPolicyFor(Piece::class))->not->toBeNull()
        ->and(Gate::forUser($user)->denies('viewAny', Piece::class))->toBeTrue()
        ->and(Gate::forUser($user)->denies('view', $this->piece))->toBeTrue();
});
