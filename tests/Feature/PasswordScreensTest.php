<?php

use App\Actions\CreateAccountAction;
use App\Filament\Auth\PortalRequestPasswordReset;
use App\Filament\Auth\PortalResetPassword;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    app()->setLocale('es');
});

it('RF-29: la pantalla de acceso de cada portal ofrece la recuperación de contraseña', function (string $panel, string $login) {
    $this->get($login)
        ->assertOk()
        ->assertSee(route("filament.{$panel}.auth.password-reset.request"));
})->with([
    'staff' => ['staff', '/staff/login'],
    'portal de clientes' => ['client', '/portal/login'],
]);

it('RF-29: pedir la recuperación con una cuenta activa envía un enlace nuevo', function (string $panel, string $role) {
    Notification::fake();
    Filament::setCurrentPanel($panel);
    $user = User::factory()->{$role}()->create(['email' => 'ana@example.com']);

    Livewire::test(PortalRequestPasswordReset::class)
        ->fillForm(['email' => 'ana@example.com'])
        ->call('request')
        ->assertNotified(__('auth.password_reset_requested'));

    Notification::assertSentTo($user, ResetPassword::class);
})->with([
    'staff' => ['staff', 'staff'],
    'portal de clientes' => ['client', 'client'],
]);

it('RF-30: un email inexistente o de cuenta inactiva recibe el mismo aviso y no se envía ningún correo', function (string $email) {
    Notification::fake();
    Filament::setCurrentPanel('staff');
    User::factory()->staff()->inactive()->create(['email' => 'inactiva@example.com']);

    Livewire::test(PortalRequestPasswordReset::class)
        ->fillForm(['email' => $email])
        ->call('request')
        ->assertNotified(__('auth.password_reset_requested'));

    Notification::assertNothingSent();
})->with([
    'inexistente' => 'nadie@example.com',
    'inactiva' => 'inactiva@example.com',
]);

it('RF-5: el mail de alta apunta a la pantalla de definición del portal que corresponde al rol', function (string $role, string $panel) {
    Notification::fake();
    $actor = User::factory()->admin()->create();

    $user = app(CreateAccountAction::class)->handle([
        'name' => 'Ana Pérez',
        'email' => 'ana@example.com',
        'role' => $role,
    ], $actor);

    $url = null;

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user, &$url) {
        $url = $notification->toMail($user)->actionUrl;

        return true;
    });

    expect($url)->toStartWith(route("filament.{$panel}.auth.password-reset.reset"));

    $this->get($url)->assertOk();
})->with([
    'admin' => ['admin', 'staff'],
    'staff' => ['staff', 'staff'],
    'client' => ['client', 'client'],
]);

it('RF-27: con un enlace vigente define la contraseña y el enlace queda invalidado', function (string $panel, string $role) {
    Filament::setCurrentPanel($panel);
    $user = User::factory()->{$role}()->create(['password' => null]);
    $token = Password::createToken($user);

    Livewire::test(PortalResetPassword::class, ['email' => $user->email, 'token' => $token])
        ->fillForm(['password' => 'una-clave-segura', 'passwordConfirmation' => 'una-clave-segura'])
        ->call('resetPassword')
        ->assertHasNoFormErrors()
        ->assertRedirect(Filament::getPanel($panel)->getLoginUrl());

    expect(Hash::check('una-clave-segura', $user->refresh()->password))->toBeTrue()
        ->and(Password::tokenExists($user, $token))->toBeFalse();
})->with([
    'staff' => ['staff', 'staff'],
    'portal de clientes' => ['client', 'client'],
]);

it('RF-28: un enlace ya usado o vencido se rechaza y ofrece pedir uno nuevo', function (bool $expired) {
    Filament::setCurrentPanel('staff');
    $user = User::factory()->staff()->create(['password' => null]);
    $token = Password::createToken($user);

    if ($expired) {
        $this->travel(1441)->minutes();
    } else {
        Password::reset(
            ['email' => $user->email, 'token' => $token, 'password' => 'otra-clave-segura'],
            fn (User $account, string $password) => $account->forceFill(['password' => $password])->save(),
        );
        $user->refresh();
    }

    $passwordBefore = $user->password;

    Livewire::test(PortalResetPassword::class, ['email' => $user->email, 'token' => $token])
        ->fillForm(['password' => 'una-clave-segura', 'passwordConfirmation' => 'una-clave-segura'])
        ->call('resetPassword')
        ->assertNotified(__('auth.invalid_password_link'))
        ->assertNoRedirect();

    expect($user->refresh()->password)->toBe($passwordBefore);
})->with([
    'vencido' => true,
    'ya usado' => false,
]);

it('RNF-1: la pantalla de definición rechaza contraseñas de menos de 8 caracteres', function () {
    Filament::setCurrentPanel('staff');
    $user = User::factory()->staff()->create(['password' => null]);
    $token = Password::createToken($user);

    Livewire::test(PortalResetPassword::class, ['email' => $user->email, 'token' => $token])
        ->fillForm(['password' => '1234567', 'passwordConfirmation' => '1234567'])
        ->call('resetPassword')
        ->assertHasFormErrors(['password']);

    expect($user->refresh()->password)->toBeNull()
        ->and(Password::tokenExists($user, $token))->toBeTrue();
});
