<?php

use App\Enums\AccessMethod;
use App\Enums\AccessOutcome;
use App\Enums\AccessPortal;
use App\Models\AccessLog;
use App\Models\User;

it('RF-36: el asiento de ingreso resuelve la cuenta y sus enums', function () {
    $user = User::factory()->create();

    $log = AccessLog::factory()->for($user)->create([
        'email_used' => $user->email,
        'portal' => AccessPortal::Staff,
        'method' => AccessMethod::Password,
        'outcome' => AccessOutcome::Success,
    ]);

    expect($log->user->is($user))->toBeTrue()
        ->and($log->portal)->toBe(AccessPortal::Staff)
        ->and($log->method)->toBe(AccessMethod::Password)
        ->and($log->outcome)->toBe(AccessOutcome::Success);
});

it('RF-37: el rechazo de un email sin cuenta no tiene usuario y guarda el motivo', function () {
    $log = AccessLog::factory()->create([
        'user_id' => null,
        'email_used' => 'nadie@example.com',
        'portal' => AccessPortal::Client,
        'method' => AccessMethod::Google,
        'outcome' => AccessOutcome::Rejected,
        'rejection_reason' => 'account_not_found',
    ]);

    expect($log->user)->toBeNull()
        ->and($log->rejection_reason)->toBe('account_not_found')
        ->and($log->outcome)->toBe(AccessOutcome::Rejected);
});

it('RF-36, RF-37: el asiento no lleva updated_at', function () {
    expect((new AccessLog)->getUpdatedAtColumn())->toBeNull();
});
