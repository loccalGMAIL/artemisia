<?php

use App\Actions\AttemptLoginAction;
use App\Enums\AccessMethod;
use App\Enums\AccessPortal;
use App\Enums\AccessRejection;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    app()->setLocale('es');
});

function loginWithPassword(string $email, string $password)
{
    return app(AttemptLoginAction::class)->handle(
        ['email' => $email, 'password' => $password],
        AccessPortal::Staff,
        AccessMethod::Password,
    );
}

function failLogin(string $email, int $times): void
{
    foreach (range(1, $times) as $ignored) {
        expect(loginWithPassword($email, 'incorrecta')->rejection)->toBe(AccessRejection::InvalidCredentials);
    }
}

it('RNF-2: rechaza el sexto intento dentro del minuto aunque la contraseña sea correcta', function (bool $accountExists) {
    if ($accountExists) {
        User::factory()->staff()->create(['email' => 'ana@example.com']);
    }

    failLogin('ana@example.com', 5);

    $sixth = loginWithPassword('ana@example.com', 'password');

    expect($sixth->successful)->toBeFalse()
        ->and($sixth->rejection)->toBe(AccessRejection::TooManyAttempts)
        ->and($sixth->message())->toContain('Demasiados intentos');
})->with([
    'cuenta existente' => true,
    'email sin cuenta' => false,
]);

it('RNF-2: con cuatro intentos fallidos todavía se evalúa normalmente', function () {
    User::factory()->staff()->create(['email' => 'ana@example.com']);

    failLogin('ana@example.com', 4);

    expect(loginWithPassword('ana@example.com', 'password')->successful)->toBeTrue();
});

it('RNF-2: al vencer los 60 segundos vuelve a aceptar intentos automáticamente', function () {
    User::factory()->staff()->create(['email' => 'ana@example.com']);

    failLogin('ana@example.com', 5);

    expect(loginWithPassword('ana@example.com', 'password')->rejection)->toBe(AccessRejection::TooManyAttempts);

    $this->travel(61)->seconds();

    expect(loginWithPassword('ana@example.com', 'password')->successful)->toBeTrue();
});

it('RNF-2: el límite es por email y no afecta a otras cuentas', function () {
    User::factory()->staff()->create(['email' => 'ana@example.com']);
    User::factory()->staff()->create(['email' => 'luis@example.com']);

    failLogin('ana@example.com', 5);

    expect(loginWithPassword('luis@example.com', 'password')->successful)->toBeTrue();
});

it('RNF-2: el email se normaliza al contar los intentos', function () {
    failLogin('ana@example.com', 3);
    failLogin('  ANA@example.com ', 2);

    expect(loginWithPassword('Ana@Example.com', 'password')->rejection)->toBe(AccessRejection::TooManyAttempts);
});
