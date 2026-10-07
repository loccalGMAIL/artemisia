<?php

use App\Actions\RequestPasswordResetAction;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    app()->setLocale('es');
    Notification::fake();
});

it('RF-29: un email de cuenta activa recibe un enlace nuevo', function () {
    $user = User::factory()->staff()->create(['email' => 'ana@example.com']);

    $message = app(RequestPasswordResetAction::class)->handle('ana@example.com');

    Notification::assertSentTo($user, ResetPassword::class);

    expect($message)->toBe(__('auth.password_reset_requested'));
});

it('RF-29: normaliza el email al buscar la cuenta', function () {
    $user = User::factory()->staff()->create(['email' => 'ana@example.com']);

    app(RequestPasswordResetAction::class)->handle('  ANA@Example.com ');

    Notification::assertSentTo($user, ResetPassword::class);
});

it('RF-30: un email inexistente o de cuenta inactiva da el mismo mensaje y no envía ningún correo', function () {
    User::factory()->staff()->inactive()->create(['email' => 'inactiva@example.com']);
    User::factory()->staff()->create(['email' => 'activa@example.com']);

    $active = app(RequestPasswordResetAction::class)->handle('activa@example.com');

    Notification::fake();

    $unknown = app(RequestPasswordResetAction::class)->handle('nadie@example.com');
    $inactive = app(RequestPasswordResetAction::class)->handle('inactiva@example.com');

    Notification::assertNothingSent();

    expect($unknown)->toBe($active)
        ->and($inactive)->toBe($active)
        ->and($active)->toContain('Si el email corresponde a una cuenta activa');
});
