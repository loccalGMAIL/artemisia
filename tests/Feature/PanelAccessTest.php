<?php

use App\Filament\Client\Pages\Auth\Login as ClientLogin;
use App\Filament\Staff\Pages\Auth\Login as StaffLogin;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    app()->setLocale('es');
});

it('RF-23: lleva a un visitante sin sesión a la pantalla de acceso del portal pedido', function (string $path, string $login) {
    $this->get($path)->assertRedirect($login);
})->with([
    'staff' => ['/staff', '/staff/login'],
    'portal de clientes' => ['/portal', '/portal/login'],
]);

it('RF-24: niega el acceso a una cuenta con sesión cuyo rol no corresponde al portal', function (string $role, string $path) {
    $user = User::factory()->{$role}()->create();

    $this->actingAs($user)->get($path)->assertForbidden();
})->with([
    'client en staff' => ['client', '/staff'],
    'staff en portal' => ['staff', '/portal'],
    'admin en portal' => ['admin', '/portal'],
]);

it('RF-16: una cuenta con el rol correcto llega a la página inicial de su portal', function (string $role, string $panel, string $login) {
    $user = User::factory()->{$role}()->create();

    Filament::setCurrentPanel($panel);

    Livewire::test($login)
        ->fillForm(['email' => $user->email, 'password' => 'password'])
        ->call('authenticate')
        ->assertHasNoFormErrors()
        ->assertRedirect(Filament::getPanel($panel)->getUrl());

    $this->assertAuthenticatedAs($user);
    $this->get(Filament::getPanel($panel)->getUrl())->assertOk();
})->with([
    'admin en staff' => ['admin', 'staff', StaffLogin::class],
    'staff en staff' => ['staff', 'staff', StaffLogin::class],
    'client en portal' => ['client', 'client', ClientLogin::class],
]);

it('RF-21, RF-22: la pantalla de acceso rechaza a una cuenta con rol ajeno e indica que no accede a ese portal', function (string $role, string $panel, string $login) {
    $user = User::factory()->{$role}()->create();

    Filament::setCurrentPanel($panel);

    Livewire::test($login)
        ->fillForm(['email' => $user->email, 'password' => 'password'])
        ->call('authenticate')
        ->assertHasFormErrors(['email']);

    $this->assertGuest();
})->with([
    'client en staff' => ['client', 'staff', StaffLogin::class],
    'staff en portal' => ['staff', 'client', ClientLogin::class],
    'admin en portal' => ['admin', 'client', ClientLogin::class],
]);

it('RF-26: al cerrar sesión vuelve a la pantalla de acceso de su portal', function (string $role, string $panel, string $login) {
    $user = User::factory()->{$role}()->create();

    $this->actingAs($user)
        ->post(route("filament.{$panel}.auth.logout"))
        ->assertRedirect($login);

    $this->assertGuest();
})->with([
    'staff' => ['staff', 'staff', '/staff/login'],
    'client' => ['client', 'client', '/portal/login'],
]);
