<?php

use App\Actions\ChangeOwnPasswordAction;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    app()->setLocale('es');
});

it('RF-10: al cambiar la contraseña caen las otras sesiones de la cuenta y sigue abierta la que hizo el cambio', function (string $role, string $path, string $login) {
    $account = User::factory()->{$role}()->create(['password' => 'clave-actual-1']);

    $this->actingAs($account)->get($path)->assertOk();

    // The hash the account holds in every session it opened with the old password.
    $oldHash = session('password_hash_web');

    expect($oldHash)->not->toBeNull();

    app(ChangeOwnPasswordAction::class)->handle($account, 'clave-actual-1', 'clave-nueva-2');

    $this->get($path)->assertOk();
    $this->assertAuthenticatedAs($account);

    $this->flushSession()
        ->actingAs(User::query()->findOrFail($account->id))
        ->withSession(['password_hash_web' => $oldHash])
        ->get($path)
        ->assertRedirect($login);

    $this->assertGuest();
})->with([
    'staff' => ['staff', '/staff', '/staff/login'],
    'client' => ['client', '/portal', '/portal/login'],
]);
