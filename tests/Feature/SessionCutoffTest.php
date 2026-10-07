<?php

use App\Actions\DeactivateAccountAction;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('RF-33: una cuenta con sesión abierta que se desactiva queda fuera en su siguiente solicitud', function (string $role, string $path, string $login) {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->{$role}()->create();

    $this->actingAs($user)->get($path)->assertOk();

    // Another process deactivates the account: the session holder keeps its stale instance.
    app(DeactivateAccountAction::class)->handle(User::query()->findOrFail($user->id), $admin);

    $this->get($path)->assertRedirect($login);

    $this->assertGuest();
})->with([
    'staff' => ['staff', '/staff', '/staff/login'],
    'admin' => ['admin', '/staff', '/staff/login'],
    'client' => ['client', '/portal', '/portal/login'],
]);

it('RF-33: una cuenta activa sigue navegando sin cortes', function () {
    $user = User::factory()->staff()->create();

    $this->actingAs($user)->get('/staff')->assertOk();
    $this->get('/staff')->assertOk();

    $this->assertAuthenticatedAs($user);
});
