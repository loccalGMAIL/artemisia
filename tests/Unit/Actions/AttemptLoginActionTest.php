<?php

use App\Actions\AttemptLoginAction;
use App\Enums\AccessMethod;
use App\Enums\AccessPortal;
use App\Enums\AccessRejection;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Laravel\Socialite\Two\User as SocialiteUser;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    app()->setLocale('es');
});

function googleUser(string $email): SocialiteUser
{
    return (new SocialiteUser)->map(['id' => 'google-id', 'name' => 'Persona', 'email' => $email]);
}

function attemptPassword(string $email, string $password, AccessPortal $portal = AccessPortal::Staff)
{
    return app(AttemptLoginAction::class)->handle(
        ['email' => $email, 'password' => $password],
        $portal,
        AccessMethod::Password,
    );
}

function attemptGoogle(string $email, AccessPortal $portal = AccessPortal::Staff)
{
    return app(AttemptLoginAction::class)->handle(googleUser($email), $portal, AccessMethod::Google);
}

it('RF-18: rechaza credenciales que no coinciden con un mensaje que no revela si el email existe', function () {
    User::factory()->staff()->create(['email' => 'ana@example.com']);

    $unknownEmail = attemptPassword('nadie@example.com', 'password');
    $wrongPassword = attemptPassword('ana@example.com', 'incorrecta');

    expect($unknownEmail->successful)->toBeFalse()
        ->and($unknownEmail->user)->toBeNull()
        ->and($unknownEmail->rejection)->toBe(AccessRejection::InvalidCredentials)
        ->and($wrongPassword->rejection)->toBe(AccessRejection::InvalidCredentials)
        ->and($wrongPassword->message())->toBe($unknownEmail->message());
});

it('RF-18, RF-19: una contraseña incorrecta contra una cuenta inactiva da el mensaje genérico, no el de cuenta deshabilitada', function () {
    User::factory()->staff()->inactive()->create(['email' => 'ana@example.com']);

    $result = attemptPassword('ana@example.com', 'incorrecta');

    expect($result->rejection)->toBe(AccessRejection::InvalidCredentials);
});

it('RF-20: rechaza a una cuenta inactiva con credenciales correctas, por email y por Google', function () {
    User::factory()->staff()->inactive()->create(['email' => 'ana@example.com']);

    $byPassword = attemptPassword('ana@example.com', 'password');
    $byGoogle = attemptGoogle('ana@example.com');

    expect($byPassword->rejection)->toBe(AccessRejection::AccountInactive)
        ->and($byPassword->message())->toContain('deshabilitada')
        ->and($byGoogle->rejection)->toBe(AccessRejection::AccountInactive);
});

it('RF-21, RF-22: rechaza al rol que no corresponde al portal', function (string $role, AccessPortal $portal) {
    User::factory()->{$role}()->create(['email' => 'ana@example.com']);

    $byPassword = attemptPassword('ana@example.com', 'password', $portal);
    $byGoogle = attemptGoogle('ana@example.com', $portal);

    expect($byPassword->rejection)->toBe(AccessRejection::WrongPortal)
        ->and($byPassword->message())->toContain('no accede a ese portal')
        ->and($byGoogle->rejection)->toBe(AccessRejection::WrongPortal);
})->with([
    'client en staff' => ['client', AccessPortal::Staff],
    'staff en client' => ['staff', AccessPortal::Client],
    'admin en client' => ['admin', AccessPortal::Client],
]);

it('RF-19: evalúa primero si la cuenta está activa y después el portal', function () {
    User::factory()->client()->inactive()->create(['email' => 'ana@example.com']);

    $result = attemptPassword('ana@example.com', 'password', AccessPortal::Staff);

    expect($result->rejection)->toBe(AccessRejection::AccountInactive);
});

it('RF-17: un email de Google sin cuenta no crea ninguna y se informa que no está habilitada', function () {
    $result = attemptGoogle('nadie@example.com');

    expect($result->successful)->toBeFalse()
        ->and($result->user)->toBeNull()
        ->and($result->rejection)->toBe(AccessRejection::AccountNotEnabled)
        ->and($result->message())->toContain('agencia')
        ->and(User::query()->count())->toBe(0);
});

it('RF-14, RF-19: el ingreso válido identifica la cuenta por email sin distinguir mayúsculas ni espacios', function (string $role, AccessPortal $portal) {
    $user = User::factory()->{$role}()->create(['email' => 'ana@example.com']);

    $byPassword = attemptPassword('  ANA@example.com ', 'password', $portal);
    $byGoogle = attemptGoogle('Ana@Example.com', $portal);

    expect($byPassword->successful)->toBeTrue()
        ->and($byPassword->rejection)->toBeNull()
        ->and($byPassword->user->is($user))->toBeTrue()
        ->and($byGoogle->successful)->toBeTrue()
        ->and($byGoogle->user->is($user))->toBeTrue();
})->with([
    'admin en staff' => ['admin', AccessPortal::Staff],
    'staff en staff' => ['staff', AccessPortal::Staff],
    'client en client' => ['client', AccessPortal::Client],
]);
