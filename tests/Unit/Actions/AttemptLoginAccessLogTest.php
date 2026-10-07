<?php

use App\Actions\AttemptLoginAction;
use App\Enums\AccessMethod;
use App\Enums\AccessOutcome;
use App\Enums\AccessPortal;
use App\Models\AccessLog;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Laravel\Socialite\Two\User as SocialiteUser;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('RF-36: cada ingreso exitoso deja un asiento nuevo con fecha, hora, portal y método sin sobrescribir los anteriores', function () {
    $user = User::factory()->staff()->create(['email' => 'ana@example.com']);
    $action = app(AttemptLoginAction::class);

    $this->travelTo(now()->setDate(2026, 10, 1)->setTime(10, 0));
    $action->handle(['email' => 'ana@example.com', 'password' => 'password'], AccessPortal::Staff, AccessMethod::Password);
    $first = AccessLog::query()->sole();

    $this->travelTo(now()->setDate(2026, 10, 1)->setTime(11, 30));
    $google = (new SocialiteUser)->map(['id' => 'g-1', 'name' => 'Ana', 'email' => 'ana@example.com']);
    $action->handle($google, AccessPortal::Staff, AccessMethod::Google);

    $logs = AccessLog::query()->orderBy('id')->get();

    expect($logs)->toHaveCount(2)
        ->and($logs[0]->is($first))->toBeTrue()
        ->and($logs[0]->created_at->toDateTimeString())->toBe('2026-10-01 10:00:00')
        ->and($logs[0]->method)->toBe(AccessMethod::Password)
        ->and($logs[1]->created_at->toDateTimeString())->toBe('2026-10-01 11:30:00')
        ->and($logs[1]->method)->toBe(AccessMethod::Google);

    foreach ($logs as $log) {
        expect($log->user_id)->toBe($user->id)
            ->and($log->email_used)->toBe('ana@example.com')
            ->and($log->portal)->toBe(AccessPortal::Staff)
            ->and($log->outcome)->toBe(AccessOutcome::Success)
            ->and($log->rejection_reason)->toBeNull();
    }
});

it('RF-37: un rechazo deja el email usado, el motivo, el portal y la fecha', function () {
    $this->travelTo(now()->setDate(2026, 10, 2)->setTime(9, 15));

    app(AttemptLoginAction::class)->handle(
        ['email' => 'nadie@example.com', 'password' => 'x'],
        AccessPortal::Client,
        AccessMethod::Password,
    );

    $log = AccessLog::query()->sole();

    expect($log->user_id)->toBeNull()
        ->and($log->email_used)->toBe('nadie@example.com')
        ->and($log->portal)->toBe(AccessPortal::Client)
        ->and($log->method)->toBe(AccessMethod::Password)
        ->and($log->outcome)->toBe(AccessOutcome::Rejected)
        ->and($log->rejection_reason)->toBe('invalid_credentials')
        ->and($log->created_at->toDateTimeString())->toBe('2026-10-02 09:15:00');
});

it('RF-37: el rechazo de una cuenta existente guarda su usuario y el motivo', function () {
    $user = User::factory()->client()->create(['email' => 'ana@example.com']);

    app(AttemptLoginAction::class)->handle(
        ['email' => 'ana@example.com', 'password' => 'password'],
        AccessPortal::Staff,
        AccessMethod::Password,
    );

    $log = AccessLog::query()->sole();

    expect($log->user_id)->toBe($user->id)
        ->and($log->outcome)->toBe(AccessOutcome::Rejected)
        ->and($log->rejection_reason)->toBe('wrong_portal');
});

it('RF-37: el rechazo de un email de Google sin cuenta deja el motivo account_not_enabled', function () {
    $google = (new SocialiteUser)->map(['id' => 'g-2', 'name' => 'Nadie', 'email' => 'nadie@example.com']);

    app(AttemptLoginAction::class)->handle($google, AccessPortal::Client, AccessMethod::Google);

    $log = AccessLog::query()->sole();

    expect($log->method)->toBe(AccessMethod::Google)
        ->and($log->email_used)->toBe('nadie@example.com')
        ->and($log->rejection_reason)->toBe('account_not_enabled');
});
