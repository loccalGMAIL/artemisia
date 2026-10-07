<?php

use App\Actions\SetPasswordAction;
use App\Exceptions\InvalidPasswordLinkException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    app()->setLocale('es');
});

it('RF-27: con un enlace vigente establece la contraseña y el enlace queda invalidado', function () {
    $user = User::factory()->staff()->create(['password' => null]);
    $token = Password::createToken($user);

    app(SetPasswordAction::class)->handle($token, $user->email, 'una-clave-segura');

    expect(Hash::check('una-clave-segura', $user->refresh()->password))->toBeTrue()
        ->and(Password::tokenExists($user, $token))->toBeFalse();
});

it('RF-28: rechaza un enlace ya usado y ofrece pedir uno nuevo', function () {
    $user = User::factory()->staff()->create(['password' => null]);
    $token = Password::createToken($user);
    app(SetPasswordAction::class)->handle($token, $user->email, 'una-clave-segura');

    expect(fn () => app(SetPasswordAction::class)->handle($token, $user->email, 'otra-clave-segura'))
        ->toThrow(InvalidPasswordLinkException::class, 'Solicite uno nuevo');

    expect(Hash::check('una-clave-segura', $user->refresh()->password))->toBeTrue();
});

it('RF-28, RNF-3: rechaza un enlace vencido a las 24 horas y ofrece pedir uno nuevo', function () {
    $user = User::factory()->staff()->create(['password' => null]);
    $token = Password::createToken($user);

    $this->travel(1441)->minutes();

    expect(fn () => app(SetPasswordAction::class)->handle($token, $user->email, 'una-clave-segura'))
        ->toThrow(InvalidPasswordLinkException::class, 'Solicite uno nuevo');

    expect($user->refresh()->password)->toBeNull();
});

it('RNF-3: el enlace sigue vigente antes de cumplir las 24 horas', function () {
    $user = User::factory()->staff()->create(['password' => null]);
    $token = Password::createToken($user);

    $this->travel(1439)->minutes();

    app(SetPasswordAction::class)->handle($token, $user->email, 'una-clave-segura');

    expect(Hash::check('una-clave-segura', $user->refresh()->password))->toBeTrue();
});

it('RF-28: rechaza un token que no corresponde al email', function () {
    $user = User::factory()->staff()->create(['password' => null]);
    $other = User::factory()->staff()->create(['password' => null]);
    $token = Password::createToken($user);

    expect(fn () => app(SetPasswordAction::class)->handle($token, $other->email, 'una-clave-segura'))
        ->toThrow(InvalidPasswordLinkException::class);

    expect($other->refresh()->password)->toBeNull();
});

it('RNF-1: rechaza contraseñas de menos de 8 caracteres sin consumir el enlace', function () {
    $user = User::factory()->staff()->create(['password' => null]);
    $token = Password::createToken($user);

    expect(fn () => app(SetPasswordAction::class)->handle($token, $user->email, '1234567'))
        ->toThrow(ValidationException::class);

    expect($user->refresh()->password)->toBeNull()
        ->and(Password::tokenExists($user, $token))->toBeTrue();
});

it('RNF-1: acepta una contraseña de exactamente 8 caracteres', function () {
    $user = User::factory()->staff()->create(['password' => null]);
    $token = Password::createToken($user);

    app(SetPasswordAction::class)->handle($token, $user->email, '12345678');

    expect(Hash::check('12345678', $user->refresh()->password))->toBeTrue();
});
